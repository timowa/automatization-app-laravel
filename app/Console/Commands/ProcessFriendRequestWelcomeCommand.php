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

    protected $description = 'Отправка одного автоприветствия по новой заявке в друзья';

    public function handle(): int
    {
        $requestId = DB::transaction(function (): ?int {
            $row = AgentVkFriendRequest::query()
                ->whereNull('message_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            return $row?->id;
        });

        if ($requestId === null) {
            $this->info('Нет заявок для автоприветствия');

            return self::SUCCESS;
        }

        ProcessFriendRequestWelcomeJob::dispatch($requestId);

        Log::channel('job')->info('Задача автоприветствия поставлена в очередь', [
            'friend_request_id' => $requestId,
        ]);
        $this->info("Обработка заявки #{$requestId}");

        return self::SUCCESS;
    }
}
