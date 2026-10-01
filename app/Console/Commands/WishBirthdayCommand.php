<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BirthdayDiscountJob;
use App\Jobs\WishHappyBirthdayJob;
use App\Models\AgentVkFriend;
use Illuminate\Console\Command;

class WishBirthdayCommand extends Command
{
    protected $signature = 'vk:wish-bd {--s|step= : 1 — поздравление, 2 — скидка}';
    protected $description = 'Ручной запуск поздравления или сообщения со скидкой';

    public function handle(): int
    {
        $friendUserId = 7622523;

        $step = (int) $this->option('step');
        if (! in_array($step, [1, 2], true)) {
            $this->error('Параметр -s должен быть равен 1 или 2');

            return self::FAILURE;
        }

        $friend = AgentVkFriend::where('user_id', $friendUserId)->first();
        if (! $friend) {
            $this->error("Друг VK с user_id {$friendUserId} не найден");

            return self::FAILURE;
        }

        if ($step === 1) {
            WishHappyBirthdayJob::dispatch((int) $friend->agent_id, (int) $friend->user_id);
        } else {
            BirthdayDiscountJob::dispatch((int) $friend->agent_id, (int) $friend->user_id);
        }

        $this->info("Задача {$step} поставлена в очередь для друга VK {$friend->user_id}");

        return self::SUCCESS;
    }
}
