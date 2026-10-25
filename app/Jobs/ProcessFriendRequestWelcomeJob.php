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

        $own = [];
        $agentId = null;
        $skippedIds = [];
        foreach ($requests as $request) {
            if (! $request instanceof AgentVkFriendRequest) {
                continue;
            }

            if ($agentId === null) {
                $agentId = (int) $request->agent_id;
            }

            if ((int) $request->agent_id !== $agentId) {
                $skippedIds[] = (int) $request->id;

                continue;
            }

            if ($this->prepareRequest($request)) {
                $own[] = $request;
            }
        }

        if ($skippedIds !== []) {
            Log::channel('job')->warning('Автоприветствие: в задаче заявки разных агентов, обработан только первый', [
                'agent_id' => $agentId,
                'skipped_friend_request_ids' => $skippedIds,
            ]);
        }

        if ($own === []) {
            return;
        }

        $this->processAgent($vkApi, $own);
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

        $ready = [];
        foreach ($requests as $request) {
            $message = $this->messageFor($request, $vkUser);
            if ($message === null) {
                continue;
            }
            $ready[] = [
                'request' => $request,
                'message' => $message,
            ];
        }

        if ($ready === []) {
            return;
        }

        $this->sendPrepared($vkApi, $ready);
    }

    /**
     * @param  list<array{request:AgentVkFriendRequest, message:array{user_id:int, message:string, attachment:?string}}>  $ready
     */
    private function sendPrepared(VkApiService $vkApi, array $ready): void
    {
        $requests = [];
        $payload = [];
        foreach ($ready as $row) {
            $requests[] = $row['request'];
            $payload[] = $row['message'];
        }

        try {
            $reports = $vkApi->sendWelcomeBatch($payload);
        } catch (\Throwable $e) {
            $this->logExecuteFailure($requests, $e);

            return;
        }

        foreach ($requests as $index => $request) {
            $report = $reports[$index] ?? null;
            if (! is_array($report)) {
                $this->logExecuteFailure([$request], new \RuntimeException('execute не вернул отчёт по заявке'));

                continue;
            }
            $this->applyReport($request, $report);
        }
    }

    /**
     * @param  list<AgentVkFriendRequest>  $requests
     */
    private function logExecuteFailure(array $requests, \Throwable $e): void
    {
        if (count($requests) === 1) {
            $this->handleFailure($requests[0], $e);

            return;
        }

        $ids = array_map(fn (AgentVkFriendRequest $request): int => (int) $request->id, $requests);
        Log::channel('job')->warning('Ошибка автоприветствия: нет отчёта по заявкам', [
            'agent_id' => $requests[0]->agent_id,
            'friend_request_ids' => $ids,
        ]);
        Log::channel('vk')->error($e->getMessage(), [
            'agent_id' => $requests[0]->agent_id,
            'friend_request_ids' => $ids,
            'context' => 'friend_request_welcome',
        ]);
    }

    private function markAlreadyMessaged(AgentVkFriendRequest $request): void
    {
        $request->message_id = AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL;
        $request->save();

        Log::channel('job')->warning('Автоприветствие не отправлено', [
            'friend_request_id' => $request->id,
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
            'reason' => VkApiService::ALREADY_WROTE_ERROR,
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
     * @param  array{user_id:int, message_id:?int, error:?string}  $report
     */
    private function applyReport(AgentVkFriendRequest $request, array $report): void
    {
        if ((int) ($report['user_id'] ?? 0) !== (int) $request->user_id) {
            $this->logExecuteFailure([$request], new \RuntimeException('execute вернул отчёт по другому пользователю'));

            return;
        }

        $error = $report['error'] ?? null;
        if ($error === VkApiService::ALREADY_WROTE_ERROR) {
            $this->markAlreadyMessaged($request);

            return;
        }

        $messageId = $report['message_id'] ?? null;
        if (is_int($messageId) && $messageId > AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL) {
            $updated = DB::table('agent_vk_friend_requests')
                ->where('id', $request->id)
                ->whereNull('message_id')
                ->update([
                    'message_id' => $messageId,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                Log::channel('job')->warning('Автоприветствие: запись уже обработана другим процессом', [
                    'friend_request_id' => $request->id,
                    'agent_id' => $request->agent_id,
                    'user_id' => $request->user_id,
                ]);

                return;
            }

            Log::channel('job')->info('Автоприветствие на заявку в друзья отправлено', [
                'friend_request_id' => $request->id,
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
                'message_id' => $messageId,
            ]);

            return;
        }

        if (is_string($error) && $error !== '') {
            $this->handleFailure($request, new VKApiException(
                0,
                $error,
                new VKApiError([
                    'error_code' => 0,
                    'error_msg' => $error,
                ]),
            ));

            return;
        }

        Log::channel('job')->warning('Ошибка автоприветствия на заявку в друзья', [
            'friend_request_id' => $request->id,
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
        ]);
        Log::channel('vk')->error('ВКонтакте не вернул номер сообщения', [
            'friend_request_id' => $request->id,
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
            'context' => 'friend_request_welcome',
        ]);
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
                'friend_request_id' => $request->id,
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'friend_request_id' => $request->id,
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
                'context' => 'friend_request_welcome',
                'permanent' => true,
            ]);

            return;
        }

        Log::channel('job')->warning('Ошибка автоприветствия на заявку в друзья', [
            'friend_request_id' => $request->id,
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
        ]);
        Log::channel('vk')->error($e->getMessage(), [
            'friend_request_id' => $request->id,
            'agent_id' => $request->agent_id,
            'user_id' => $request->user_id,
            'context' => 'friend_request_welcome',
        ]);
    }
}
