<?php

declare(strict_types=1);

namespace App\Actions\Vk;

use App\Enums\Vk\FriendStatus;
use App\Enums\Vk\LastSeenPlatform;
use App\Enums\Vk\Relation;
use App\Enums\Vk\Sex;
use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncVkUserAction
{
    public function __construct(private readonly VkApiService $vkApi)
    {
    }

    public function execute(VkUser $vkUser): void
    {
        if (!$vkUser->exists()) {
            return;
        }

        $token = $vkUser->getToken();
        if ($token === '') {
            Log::channel('vk-sync')->warning('Пропуск синхронизации: отсутствует токен', [
                'agent_id' => $vkUser->getAgentId(),
                'vk_user_id' => $vkUser->vk_user_id,
            ]);
            return;
        }

        try {
            $this->vkApi->setToken($token);

            $response = $this->vkApi->getClient()->users()->get($token, [
                'user_ids' => [$vkUser->vk_user_id],
                'fields' => [
                    'first_name',
                    'last_name',
                    'screen_name',
                    'domain',
                    'deactivated',
                    'is_closed',
                    'can_access_closed',
                    'sex',
                    'bdate',
                    'relation',
                    'home_town',
                    'city',
                    'country',
                    'online',
                    'last_seen',
                    'followers_count',
                    'friend_status',
                    'status',
                    'verified',
                ],
            ]);

            if (empty($response[0])) {
                Log::channel('vk-sync')->warning('VK API вернул пустой ответ', [
                    'agent_id' => $vkUser->getAgentId(),
                    'vk_user_id' => $vkUser->vk_user_id,
                ]);
                return;
            }

            $user = $response[0];
            $this->fillVkUserFromResponse($vkUser, $user);
            $vkUser->save();

            Log::channel('vk-sync')->info('Профиль VK-пользователя синхронизирован', [
                'agent_id' => $vkUser->getAgentId(),
                'vk_user_id' => $vkUser->vk_user_id,
            ]);
        } catch (Throwable $e) {
            Log::channel('vk-sync')->error('Ошибка синхронизации VK: ' . $e->getMessage(), [
                'agent_id' => $vkUser->getAgentId(),
                'vk_user_id' => $vkUser->vk_user_id,
            ]);
        }
    }

    protected function fillVkUserFromResponse(VkUser $vkUser, array $user): void
    {
        $vkUser->first_name = $user['first_name'] ?? null;
        $vkUser->last_name = $user['last_name'] ?? null;
        $vkUser->screen_name = $user['screen_name'] ?? null;
        $vkUser->domain = $user['domain'] ?? null;
        $vkUser->deactivated = $user['deactivated'] ?? null;
        $vkUser->is_closed = (bool) ($user['is_closed'] ?? false);
        $vkUser->can_access_closed = (bool) ($user['can_access_closed'] ?? false);
        $vkUser->sex = isset($user['sex']) ? Sex::tryFrom((int) $user['sex'])?->value : null;
        $vkUser->bdate = $vkUser->normalizeBdate($user['bdate'] ?? null);
        $vkUser->relation = isset($user['relation']) ? Relation::tryFrom((int) $user['relation'])?->value : null;
        $vkUser->home_town = $user['home_town'] ?? null;
        $vkUser->city_id = isset($user['city']['id']) ? (int) $user['city']['id'] : null;
        $vkUser->city_name = $user['city']['title'] ?? null;
        $vkUser->country_id = isset($user['country']['id']) ? (int) $user['country']['id'] : null;
        $vkUser->country_name = $user['country']['title'] ?? null;
        $vkUser->online = (bool) ($user['online'] ?? false);
        $vkUser->last_seen_at = isset($user['last_seen']['time']) ? (int) $user['last_seen']['time'] : null;
        $vkUser->last_seen_platform = isset($user['last_seen']['platform']) ? LastSeenPlatform::tryFrom((int) $user['last_seen']['platform'])?->value : null;
        $vkUser->followers_count = isset($user['followers_count']) ? (int) $user['followers_count'] : null;
        $vkUser->friend_status = isset($user['friend_status']) ? FriendStatus::tryFrom((int) $user['friend_status'])?->value : null;
        $vkUser->status = $user['status'] ?? null;
        $vkUser->verified = (bool) ($user['verified'] ?? false);
        $vkUser->raw = $user;
    }
}
