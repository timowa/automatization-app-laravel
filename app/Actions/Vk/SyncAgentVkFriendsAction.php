<?php

declare(strict_types=1);

namespace App\Actions\Vk;

use App\Helpers\VkApiErrorInspector;
use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use App\Services\Vk\VkFriendRowMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncAgentVkFriendsAction
{
    private const UPDATE_COLUMNS = [
        'bdate',
        'first_name',
        'last_name',
        'middle_name',
        'nickname',
        'sex',
        'domain',
        'city_id',
        'city_name',
        'country_id',
        'country_name',
        'mobile_phone',
        'home_phone',
        'university_id',
        'university_name',
        'faculty_id',
        'faculty_name',
        'graduation',
        'has_mobile',
        'timezone',
        'last_seen_at',
        'last_seen_platform',
        'online',
        'photo_50',
        'photo_100',
        'photo_200_orig',
        'photo_id',
        'relation',
        'status',
        'universities',
        'deactivated',
        'is_closed',
        'has_photo',
        'home_town',
        'occupation_type',
        'occupation_name',
        'education_form',
        'education_status',
        'blacklisted_by_me',
        'followers_count',
        'verified',
        'maiden_name',
        'relation_partner_id',
        'relation_partner_name',
        'schools',
        'career',
        'friend_list_names',
        'site',
        'updated_at',
    ];

    public function __construct(
        private readonly VkApiService $vkApi,
        private readonly VkFriendRowMapper $rows,
    ) {}

    /**
     * Возвращает число сохранённых друзей.
     * null — список обновлялся меньше недели назад и синхронизация пропущена.
     */
    public function execute(VkUser $vkUser, bool $force = false): ?int
    {
        if (! $force && $this->syncedWithinWeek($vkUser)) {
            return null;
        }

        $this->vkApi->setToken($vkUser->getToken());
        $friends = $this->friendsWithTimeoutRetry((int) $vkUser->vk_user_id);
        $friendListNames = $this->friendListNames($vkUser);
        $now = now();
        $rows = [];

        foreach ($friends as $friend) {
            if (! is_array($friend)) {
                continue;
            }

            $row = $this->rows->map($vkUser->getAgentId(), $friend, $vkUser, $now, $friendListNames);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('agents_vk_friends')->upsert(
                $chunk,
                ['agent_id', 'user_id'],
                self::UPDATE_COLUMNS,
            );
        }

        $this->deleteMissingFriends($vkUser->getAgentId(), array_column($rows, 'user_id'));

        $vkUser->friends_synced_at = $now;
        $vkUser->save();

        return count($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function friendsWithTimeoutRetry(int $userId): array
    {
        $last = null;

        for ($attempt = 1; $attempt <= VkApiErrorInspector::TIMEOUT_MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->vkApi->getFriends($userId);
            } catch (Throwable $e) {
                $last = $e;
                if (! VkApiErrorInspector::isTimeout($e) || $attempt === VkApiErrorInspector::TIMEOUT_MAX_ATTEMPTS) {
                    throw $e;
                }

                usleep(350_000);
            }
        }

        throw $last ?? new \RuntimeException('Не удалось получить список друзей');
    }

    /**
     * @return array<int, string>
     */
    private function friendListNames(VkUser $vkUser): array
    {
        try {
            return $this->vkApi->getFriendLists();
        } catch (Throwable $e) {
            Log::channel('vk-sync')->warning('Не удалось получить списки друзей VK: '.$e->getMessage(), [
                'agent_id' => $vkUser->getAgentId(),
            ]);

            return [];
        }
    }

    /**
     * @param  list<int>  $userIds
     */
    private function deleteMissingFriends(int $agentId, array $userIds): void
    {
        $query = DB::table('agents_vk_friends')->where('agent_id', $agentId);

        if ($userIds !== []) {
            $query->whereNotIn('user_id', $userIds);
        }

        $query->delete();
    }

    private function syncedWithinWeek(VkUser $vkUser): bool
    {
        $syncedAt = $vkUser->friends_synced_at;

        return $syncedAt !== null && $syncedAt->gt(now()->subWeek());
    }
}
