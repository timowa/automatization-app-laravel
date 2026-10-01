<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Vk\SyncAgentVkFriendsAction;
use App\Models\VkUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAgentVkFriendsCommand extends Command
{
    protected $signature = 'vk:sync-friends';
    protected $description = 'Обновление списков друзей VK агентов';

    public function handle(SyncAgentVkFriendsAction $syncFriends): int
    {
        $syncedAgents = 0;
        $syncedFriends = 0;

        foreach (VkUser::all() as $vkUser) {
            if ($vkUser->getToken() === '' || ! $vkUser->is_token_available) {
                Log::channel('job')->warning('Список друзей VK не обновлён: токен недоступен', [
                    'agent_id' => $vkUser->getAgentId(),
                ]);

                continue;
            }

            try {
                $count = $syncFriends->execute($vkUser);
                $syncedAgents++;
                $syncedFriends += $count;

                Log::channel('job')->info('Список друзей VK агента обновлён', [
                    'agent_id' => $vkUser->getAgentId(),
                    'friends_count' => $count,
                ]);
            } catch (\Throwable $e) {
                Log::channel('job')->warning('Ошибка обновления списка друзей VK агента', [
                    'agent_id' => $vkUser->getAgentId(),
                ]);
                Log::channel('vk-sync')->error($e->getMessage(), [
                    'agent_id' => $vkUser->getAgentId(),
                    'vk_user_id' => $vkUser->vk_user_id,
                ]);
            }

            usleep(350_000);
        }

        $this->info("Обновлено агентов: {$syncedAgents}, друзей: {$syncedFriends}");

        return self::SUCCESS;
    }
}
