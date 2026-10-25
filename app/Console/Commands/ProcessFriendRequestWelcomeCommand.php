<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessFriendRequestWelcomeJob;
use App\Models\AgentVkFriendRequest;
use App\Services\Vk\VkApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFriendRequestWelcomeCommand extends Command
{
    protected $signature = 'vk:process-friend-request-welcome';

    protected $description = 'Отправка до 7 автоприветствий каждому агенту по новым заявкам в друзья';

    public function handle(): int
    {
        /** @var array<int, list<int>> $byAgent */
        $byAgent = DB::transaction(function (): array {
            $rows = AgentVkFriendRequest::query()
                ->whereNull('message_id')
                ->where('welcome_permanently_failed', false)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'agent_id']);

            $grouped = [];
            foreach ($rows as $row) {
                $agentId = (int) $row->agent_id;
                if (count($grouped[$agentId] ?? []) >= VkApiService::WELCOME_BATCH_LIMIT) {
                    continue;
                }

                $grouped[$agentId][] = (int) $row->id;
            }

            return $grouped;
        });

        if ($byAgent === []) {
            $this->info('Нет заявок для автоприветствия');

            return self::SUCCESS;
        }

        $jobs = [];
        $delaySeconds = 0;
        // Один execute — один запрос ВК. Сдвиг как у SyncFriendRequestsCommand:
        // available_at в очереди хранится в целых секундах.
        foreach ($byAgent as $agentId => $requestIds) {
            ProcessFriendRequestWelcomeJob::dispatch($requestIds[0], $requestIds)
                ->delay(now()->addSeconds($delaySeconds));
            $delaySeconds++;

            $jobs[] = [
                'agent_id' => $agentId,
                'friend_request_ids' => $requestIds,
            ];
        }

        Log::channel('job')->info('Выборка заявок для автоприветствия', [
            'jobs' => $jobs,
        ]);

        foreach ($jobs as $job) {
            $this->info('Агент '.$job['agent_id'].': '.implode(', ', $job['friend_request_ids']));
        }

        return self::SUCCESS;
    }
}
