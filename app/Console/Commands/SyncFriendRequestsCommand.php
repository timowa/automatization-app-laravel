<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncAgentFriendRequestsJob;
use App\Models\Setting;
use App\Models\VkUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFriendRequestsCommand extends Command
{
    protected $signature = 'vk:sync-friend-requests';

    protected $description = 'Проверка входящих заявок в друзья у агентов с автоприветствием';

    public function handle(): int
    {
        $agentIds = Setting::query()
            ->where('friend_request_welcome_enabled', true)
            ->pluck('agent_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($agentIds === []) {
            $this->info('Нет агентов с включённым автоприветствием');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $delaySeconds = 0;

        foreach ($agentIds as $agentId) {
            $vkUser = VkUser::query()->where('agent_id', $agentId)->first();
            if ($vkUser === null || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
                Log::channel('job')->warning('Проверка заявок в друзья пропущена: токен недоступен', [
                    'agent_id' => $agentId,
                ]);

                continue;
            }

            SyncAgentFriendRequestsJob::dispatch($agentId)->delay(now()->addSeconds($delaySeconds));
            $delaySeconds += 1;
            $dispatched++;
        }

        Log::channel('job')->info('Задачи проверки заявок в друзья поставлены в очередь', [
            'dispatched' => $dispatched,
        ]);
        $this->info("Поставлено задач: {$dispatched}");

        return self::SUCCESS;
    }
}
