<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Vk\SyncVkUserAction;
use App\Models\VkUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncVkUsersCommand extends Command
{
    protected $signature = 'vk:sync-users';
    protected $description = 'Синхронизация VK-профилей агентов';

    public function handle(SyncVkUserAction $syncAction): int
    {
        $vkUsers = VkUser::all();
        $count = count($vkUsers);

        Log::channel('vk-sync')->info('Начало массовой синхронизации VK-пользователей', ['count' => $count]);

        foreach ($vkUsers as $vkUser) {
            $syncAction->execute($vkUser);
            usleep(333_333);
        }

        Log::channel('vk-sync')->info('Массовая синхронизация VK-пользователей завершена', ['count' => $count]);

        $this->info('Синхронизация завершена');
        return self::SUCCESS;
    }
}
