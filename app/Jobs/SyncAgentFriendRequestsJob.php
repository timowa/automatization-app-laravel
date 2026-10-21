<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentVkFriendRequest;
use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAgentFriendRequestsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $agentId
    ) {}

    public function handle(VkApiService $vkApi): void
    {
        $agent = Agent::with(['setting', 'vkUser'])->find($this->agentId);
        if ($agent === null) {
            Log::channel('job')->warning('Синхронизация заявок в друзья пропущена: агент не найден', [
                'agent_id' => $this->agentId,
            ]);

            return;
        }

        $setting = $agent->setting;
        if ($setting === null || ! $setting->friend_request_welcome_enabled) {
            Log::channel('job')->info('Синхронизация заявок в друзья пропущена: автоприветствие выключено', [
                'agent_id' => $this->agentId,
            ]);

            return;
        }

        $vkUser = $agent->vkUser;
        if (! $vkUser instanceof VkUser || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
            Log::channel('job')->warning('Синхронизация заявок в друзья пропущена: токен недоступен', [
                'agent_id' => $this->agentId,
            ]);

            return;
        }

        try {
            $vkApi->setToken($vkUser->getToken());
            $items = $vkApi->getFriendRequests();
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка синхронизации заявок в друзья', [
                'agent_id' => $this->agentId,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $this->agentId,
                'context' => 'friends.getRequests',
            ]);

            return;
        }

        $existingUserIds = AgentVkFriendRequest::query()
            ->where('agent_id', $agent->id)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $existingMap = array_fill_keys($existingUserIds, true);

        $inserted = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $userId = (int) ($item['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (isset($existingMap[$userId])) {
                continue;
            }

            $deactivated = isset($item['deactivated']) ? trim((string) $item['deactivated']) : '';
            if ($deactivated !== '') {
                continue;
            }

            $isClosed = (bool) ($item['is_closed'] ?? false);
            if ($isClosed) {
                continue;
            }

            $mutual = $item['mutual'] ?? null;
            $mutualCount = is_array($mutual) ? (int) ($mutual['count'] ?? 0) : 0;

            AgentVkFriendRequest::query()->create([
                'agent_id' => $agent->id,
                'user_id' => $userId,
                'first_name' => isset($item['first_name']) ? (string) $item['first_name'] : null,
                'last_name' => isset($item['last_name']) ? (string) $item['last_name'] : null,
                'is_closed' => false,
                'can_access_closed' => (bool) ($item['can_access_closed'] ?? true),
                'deactivated' => null,
                'mutual_count' => $mutualCount,
                'track_code' => isset($item['track_code']) ? (string) $item['track_code'] : null,
                'payload' => $item,
                'message_id' => null,
            ]);

            $existingMap[$userId] = true;
            $inserted++;
        }

        if ($inserted > 0) {
            Log::channel('job')->info('Новые заявки в друзья сохранены', [
                'agent_id' => $this->agentId,
                'inserted' => $inserted,
            ]);
        } else {
            Log::channel('job')->info('Новых заявок в друзья нет', [
                'agent_id' => $this->agentId,
            ]);
        }
    }
}
