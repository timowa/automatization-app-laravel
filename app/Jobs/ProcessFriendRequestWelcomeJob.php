<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\FriendRequestWelcomeText;
use App\Helpers\VkApiErrorInspector;
use App\Models\AgentVkFriend;
use App\Models\AgentVkFriendRequest;
use App\Models\VkUser;
use App\Services\StandardTextRenderer;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use VK\Client\VKApiError;
use VK\Exceptions\VKApiException;

class ProcessFriendRequestWelcomeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const BATCH_LIMIT = VkApiService::WELCOME_BATCH_LIMIT;

    public int $tries = 1;

    public int $uniqueFor = 240;

    /**
     * @param  list<int>  $friendRequestIds
     */
    public function __construct(
        public readonly int $friendRequestId,
        public readonly array $friendRequestIds = [],
    ) {}

    public function uniqueId(): string
    {
        return 'friend-request-welcome-'.implode('-', $this->ids());
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        $ids = $this->friendRequestIds !== [] ? $this->friendRequestIds : [$this->friendRequestId];

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function handle(VkApiService $vkApi): void
    {
        $requests = AgentVkFriendRequest::query()
            ->whereIn('id', $this->ids())
            ->orderBy('id')
            ->get();

        /** @var array<int, list<AgentVkFriendRequest>> $byAgent */
        $byAgent = [];
        foreach ($requests as $request) {
            if (! $request instanceof AgentVkFriendRequest || ! $this->prepareRequest($request)) {
                continue;
            }
            $byAgent[(int) $request->agent_id][] = $request;
        }

        $agentIndex = 0;
        foreach ($byAgent as $agentRequests) {
            if ($agentIndex > 0) {
                usleep(350_000);
            }
            $agentIndex++;
            $this->processAgent($vkApi, $agentRequests);
        }
    }

    private function prepareRequest(AgentVkFriendRequest $request): bool
    {
        if ($request->message_id !== null) {
            Log::channel('job')->info('Автоприветствие пропущено: сообщение уже отмечено', [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
                'message_id' => $request->message_id,
            ]);

            return false;
        }

        $agent = $request->agent()->with(['setting.welcomeCard', 'vkUser'])->first();
        if ($agent === null) {
            Log::channel('job')->warning('Автоприветствие пропущено: агент не найден', [
                'friend_request_id' => $request->id,
            ]);

            return false;
        }

        $setting = $agent->setting;
        if ($setting === null || ! $setting->friend_request_welcome_enabled) {
            Log::channel('job')->info('Автоприветствие пропущено: настройка выключена', [
                'agent_id' => $agent->id,
                'user_id' => $request->user_id,
            ]);

            return false;
        }

        $vkUser = $agent->vkUser;
        if ($vkUser === null || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
            Log::channel('job')->warning('Автоприветствие пропущено: токен недоступен', [
                'agent_id' => $agent->id,
                'user_id' => $request->user_id,
            ]);

            return false;
        }

        $request->setRelation('agent', $agent);

        return true;
    }

    /**
     * @param  list<AgentVkFriendRequest>  $requests
     */
    private function processAgent(VkApiService $vkApi, array $requests): void
    {
        $agent = $requests[0]->agent;
        $vkUser = $agent?->vkUser;
        if ($agent === null || ! $vkUser instanceof VkUser) {
            return;
        }

        $vkApi->setToken($vkUser->getToken());

        try {
            $outgoing = $vkApi->findUsersWithOutgoingMessages(array_map(
                fn (AgentVkFriendRequest $request): int => (int) $request->user_id,
                $requests,
            ));
        } catch (\Throwable $e) {
            $this->failBatch($requests, $e);

            return;
        }

        $pending = [];
        foreach ($requests as $request) {
            if (in_array((int) $request->user_id, $outgoing, true)) {
                $this->markAlreadyMessaged($request);

                continue;
            }
            $pending[] = $request;
        }

        if ($pending === []) {
            return;
        }

        usleep(350_000);

        $messages = [];
        $byUserId = [];
        foreach ($pending as $request) {
            $built = $this->messageFor($request, $vkUser);
            if ($built === null) {
                continue;
            }
            $messages[] = $built;
            $byUserId[(int) $request->user_id] = $request;
        }

        if ($messages === []) {
            return;
        }

        try {
            $results = $vkApi->sendDirectMessages($messages);
        } catch (\Throwable $e) {
            $this->failBatch(array_values($byUserId), $e);

            return;
        }

        foreach ($results as $result) {
            $request = $byUserId[(int) $result['user_id']] ?? null;
            if ($request === null) {
                continue;
            }
            $this->applySendResult($request, $result);
        }
    }

    /**
     * @param  list<AgentVkFriendRequest>  $requests
     */
    private function failBatch(array $requests, \Throwable $e): void
    {
        if (count($requests) === 1) {
            $this->handleFailure($requests[0], $e);

            return;
        }

        Log::channel('job')->warning('Ошибка автоприветствия на заявку в друзья', [
            'agent_id' => $requests[0]->agent_id,
            'count' => count($requests),
        ]);
        Log::channel('vk')->error($e->getMessage(), [
            'agent_id' => $requests[0]->agent_id,
            'context' => 'friend_request_welcome',
            'count' => count($requests),
        ]);
    }

    private function markAlreadyMessaged(AgentVkFriendRequest $request): void
    {
        $request->message_id = AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL;
        $request->save();

        Log::channel('job')->info('Автоприветствие пропущено: агент уже писал пользователю', [
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
            'message_id' => AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL,
        ]);
    }

    /**
     * @return array{user_id:int, message:string, attachment:?string}|null
     */
    private function messageFor(AgentVkFriendRequest $request, VkUser $vkUser): ?array
    {
        $setting = $request->agent?->setting;
        $template = FriendRequestWelcomeText::resolveTemplate($setting);
        if ($template === null || trim($template) === '') {
            Log::channel('job')->warning('Автоприветствие не отправлено: нет текста', [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
            ]);

            return null;
        }

        $friendContext = new AgentVkFriend([
            'first_name' => (string) ($request->first_name ?? ''),
            'last_name' => (string) ($request->last_name ?? ''),
        ]);
        $message = StandardTextRenderer::render(
            $template,
            StandardTextRenderer::contextFrom($vkUser, $friendContext)
        );

        $attachment = null;
        $mediaId = (int) ($setting?->welcomeCard?->media_id ?? 0);
        $ownerId = (int) $vkUser->vk_user_id;
        if ($mediaId > 0 && $ownerId > 0) {
            $attachment = FriendRequestWelcomeText::photoAttachment($ownerId, $mediaId);
        }

        return [
            'user_id' => (int) $request->user_id,
            'message' => $message,
            'attachment' => $attachment,
        ];
    }

    /**
     * @param  array{user_id:int, message_id:int, error:?string, error_code:int}  $result
     */
    private function applySendResult(AgentVkFriendRequest $request, array $result): void
    {
        $messageId = (int) $result['message_id'];
        if ($messageId > AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL) {
            $updated = DB::table('agent_vk_friend_requests')
                ->where('id', $request->id)
                ->whereNull('message_id')
                ->update([
                    'message_id' => $messageId,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                Log::channel('job')->warning('Автоприветствие: запись уже обработана другим процессом', [
                    'agent_id' => $request->agent_id,
                    'user_id' => $request->user_id,
                ]);

                return;
            }

            Log::channel('job')->info('Автоприветствие на заявку в друзья отправлено', [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
                'message_id' => $messageId,
            ]);

            return;
        }

        $error = $result['error'];
        if ($error === null || $error === '') {
            Log::channel('job')->warning('Ошибка автоприветствия на заявку в друзья', [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
            ]);
            Log::channel('vk')->error('ВКонтакте не вернул номер сообщения', [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
                'context' => 'friend_request_welcome',
            ]);

            return;
        }

        $this->handleFailure($request, new VKApiException(
            (int) $result['error_code'],
            $error,
            new VKApiError([
                'error_code' => (int) $result['error_code'],
                'error_msg' => $error,
            ]),
        ));
    }

    private function handleFailure(AgentVkFriendRequest $request, \Throwable $e): void
    {
        if (VkApiErrorInspector::isPermanentDirectMessageRefusal($e)) {
            DB::table('agent_vk_friend_requests')
                ->where('id', $request->id)
                ->whereNull('message_id')
                ->update([
                    'welcome_permanently_failed' => true,
                    'updated_at' => now(),
                ]);

            Log::channel('job')->warning('Автоприветствие не отправлено: постоянный отказ ВКонтакте', [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
                'context' => 'friend_request_welcome',
                'permanent' => true,
            ]);

            return;
        }

        Log::channel('job')->warning('Ошибка автоприветствия на заявку в друзья', [
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
        ]);
        Log::channel('vk')->error($e->getMessage(), [
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
            'context' => 'friend_request_welcome',
        ]);
    }
}
