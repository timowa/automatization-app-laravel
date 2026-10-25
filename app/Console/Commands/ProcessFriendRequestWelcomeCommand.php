<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessFriendRequestWelcomeJob;
use App\Models\AgentVkFriendRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFriendRequestWelcomeCommand extends Command
{
    protected $signature = 'vk:process-friend-request-welcome';

    protected $description = 'Отправка до 25 автоприветствий по новым заявкам в друзья';

    public function handle(): int
    {
        $requestIds = DB::transaction(function (): array {
            return AgentVkFriendRequest::query()
                ->whereNull('message_id')
                ->where('welcome_permanently_failed', false)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit(ProcessFriendRequestWelcomeJob::BATCH_LIMIT)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        });

        if ($requestIds === []) {
            $this->info('Нет заявок для автоприветствия');

            return self::SUCCESS;
        }

        ProcessFriendRequestWelcomeJob::dispatch($requestIds[0], $requestIds);

        Log::channel('job')->info('Задача автоприветствия поставлена в очередь', [
            'friend_request_ids' => $requestIds,
        ]);
        $this->info('Обработка заявок: '.implode(', ', $requestIds));

        return self::SUCCESS;
    }
}
