<?php

declare(strict_types=1);

namespace App\Actions\Vk;

use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use Illuminate\Support\Facades\DB;

final class SyncAgentVkFriendsAction
{
    public function __construct(
        private readonly VkApiService $vkApi
    ) {}

    public function execute(VkUser $vkUser): int
    {
        $this->vkApi->setToken($vkUser->getToken());
        $friends = $this->vkApi->getFriends((int) $vkUser->vk_user_id);

        if ($friends === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(fn (array $friend): array => [
            'agent_id' => $vkUser->getAgentId(),
            'user_id' => (int) $friend['id'],
            'bdate' => $vkUser->normalizeBdate($friend['bdate'] ?? null),
            'first_name' => (string) ($friend['first_name'] ?? ''),
            'last_name' => (string) ($friend['last_name'] ?? ''),
            'middle_name' => $friend['middle_name'] ?? $friend['nickname'] ?? null,
            'sex' => isset($friend['sex']) ? (int) $friend['sex'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $friends);

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('agents_vk_friends')->upsert(
                $chunk,
                ['agent_id', 'user_id'],
                ['bdate', 'first_name', 'last_name', 'middle_name', 'sex', 'updated_at']
            );
        }

        return count($rows);
    }
}
