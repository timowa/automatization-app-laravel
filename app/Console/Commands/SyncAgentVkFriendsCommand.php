<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Vk\SyncAgentVkFriendsAction;
use App\Models\VkUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAgentVkFriendsCommand extends Command
{
    protected $signature = 'vk:sync-friends {--force : Обновить списки, даже если они синхронизировались меньше недели назад}';

    protected $description = 'Обновление списков друзей VK агентов';

    public function handle(SyncAgentVkFriendsAction $syncFriends): int
    {
        $syncedAgents = 0;
        $syncedFriends = 0;
        $skippedAgents = 0;
        $force = (bool) $this->option('force');

        foreach (VkUser::all() as $vkUser) {
            if ($vkUser->getToken() === '' || ! $vkUser->is_token_available) {
                Log::channel('job')->warning('Список друзей VK не обновлён: токен недоступен', [
                    'agent_id' => $vkUser->getAgentId(),
                ]);

                continue;
            }

            $calledApi = false;

            try {
                $count = $syncFriends->execute($vkUser, $force);
                $calledApi = $count !== null;

                if ($count === null) {
                    $skippedAgents++;
                    Log::channel('job')->info('Список друзей VK пропущен: обновлялся меньше недели назад', [
                        'agent_id' => $vkUser->getAgentId(),
                    ]);

                    continue;
                }

                $syncedAgents++;
                $syncedFriends += $count;

                Log::channel('job')->info('Список друзей VK агента обновлён', [
                    'agent_id' => $vkUser->getAgentId(),
                    'friends_count' => $count,
                ]);
            } catch (\Throwable $e) {
                $calledApi = true;
                Log::channel('job')->warning('Ошибка обновления списка друзей VK агента', [
                    'agent_id' => $vkUser->getAgentId(),
                ]);
                Log::channel('vk-sync')->error($e->getMessage(), [
                    'agent_id' => $vkUser->getAgentId(),
                    'vk_user_id' => $vkUser->vk_user_id,
                ]);
            }

            if ($calledApi) {
                usleep(350_000);
            }
        }

        $this->info("Обновлено агентов: {$syncedAgents}, друзей: {$syncedFriends}, пропущено: {$skippedAgents}");

        return self::SUCCESS;
    }
}
