<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\FriendRequestWelcomeText;
use App\Models\AgentVkFriendRequest;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFriendRequestWelcomeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 240;

    public function __construct(
        public readonly int $friendRequestId
    ) {}

    public function uniqueId(): string
    {
        return 'friend-request-welcome-'.$this->friendRequestId;
    }

    public function handle(VkApiService $vkApi): void
    {
        $request = AgentVkFriendRequest::query()->find($this->friendRequestId);
        if ($request === null) {
            return;
        }

        if ($request->message_id !== null) {
            Log::channel('job')->info('Автоприветствие пропущено: сообщение уже отмечено', [
                'agent_id' => $request->agent_id,
                'user_id' => $request->user_id,
                'message_id' => $request->message_id,
            ]);

            return;
        }

        $agent = $request->agent()->with(['setting.welcomeCard', 'vkUser'])->first();
        if ($agent === null) {
            Log::channel('job')->warning('Автоприветствие пропущено: агент не найден', [
                'friend_request_id' => $this->friendRequestId,
            ]);

            return;
        }

        $setting = $agent->setting;
        if ($setting === null || ! $setting->friend_request_welcome_enabled) {
            Log::channel('job')->info('Автоприветствие пропущено: настройка выключена', [
                'agent_id' => $agent->id,
                'user_id' => $request->user_id,
            ]);

            return;
        }

        $vkUser = $agent->vkUser;
        if ($vkUser === null || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
            Log::channel('job')->warning('Автоприветствие пропущено: токен недоступен', [
                'agent_id' => $agent->id,
                'user_id' => $request->user_id,
            ]);

            return;
        }

        try {
            $vkApi->setToken($vkUser->getToken());

//            if ($vkApi->hasOutgoingMessageWithUser($request->user_id)) {
//                $request->message_id = AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL;
//                $request->save();
//
//                Log::channel('job')->info('Автоприветствие пропущено: агент уже писал пользователю', [
//                    'agent_id' => $agent->id,
//                    'user_id' => $request->user_id,
//                    'message_id' => AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL,
//                ]);
//
//                return;
//            }

            usleep(350_000);

            $message = FriendRequestWelcomeText::resolve($setting, (string) $agent->name);
            $attachment = null;
            $mediaId = (int) ($setting->welcomeCard?->media_id ?? 0);
            $ownerId = (int) $vkUser->vk_user_id;
            if ($mediaId > 0 && $ownerId > 0) {
                $attachment = FriendRequestWelcomeText::photoAttachment($ownerId, $mediaId);
            }

            $response = $vkApi->sendDirectMessage($request->user_id, $message, $attachment);
            $messageId = $this->extractMessageId($response);

            if ($messageId <= AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL) {
                throw new \RuntimeException('ВКонтакте вернул некорректный message_id');
            }

            $updated = DB::table('agent_vk_friend_requests')
                ->where('id', $request->id)
                ->whereNull('message_id')
                ->update([
                    'message_id' => $messageId,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                Log::channel('job')->warning('Автоприветствие: запись уже обработана другим процессом', [
                    'agent_id' => $agent->id,
                    'user_id' => $request->user_id,
                ]);

                return;
            }

            Log::channel('job')->info('Автоприветствие на заявку в друзья отправлено', [
                'agent_id' => $agent->id,
                'user_id' => $request->user_id,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка автоприветствия на заявку в друзья', [
                'agent_id' => $agent->id,
                'user_id' => $request->user_id,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $agent->id,
                'user_id' => $request->user_id,
                'context' => 'friend_request_welcome',
            ]);
        }
    }

    private function extractMessageId(mixed $response): int
    {
        if (is_int($response) || is_numeric($response)) {
            return (int) $response;
        }

        if (is_array($response)) {
            if (isset($response[0]) && is_array($response[0]) && isset($response[0]['message_id'])) {
                return (int) $response[0]['message_id'];
            }

            if (isset($response['message_id'])) {
                return (int) $response['message_id'];
            }
        }

        return 0;
    }
}
