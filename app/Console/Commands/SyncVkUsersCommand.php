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
        $skipped = VkUser::query()
            ->where(function ($query): void {
                $query->where('is_token_available', false)
                    ->orWhereNull('vk_token')
                    ->orWhere('vk_token', '');
            })
            ->count();

        if ($skipped > 0) {
            Log::channel('vk-sync')->warning('Пропуск синхронизации пользователей с недоступным токеном', [
                'count' => $skipped,
            ]);
        }

        $vkUsers = VkUser::query()
            ->where('is_token_available', true)
            ->whereNotNull('vk_token')
            ->where('vk_token', '!=', '')
            ->orderBy('id')
            ->get();
        $count = count($vkUsers);

        Log::channel('vk-sync')->info('Начало массовой синхронизации VK-пользователей', ['count' => $count]);

        foreach ($vkUsers as $vkUser) {
            $requested = $syncAction->execute($vkUser);
            if ($requested) {
                usleep(SyncVkUserAction::REQUEST_INTERVAL_MICROSECONDS);
            }
        }

        Log::channel('vk-sync')->info('Массовая синхронизация VK-пользователей завершена', ['count' => $count]);

        $this->info('Синхронизация завершена');

        return self::SUCCESS;
    }
}
