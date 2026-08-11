<?php

declare(strict_types=1);

namespace App\Services\Vk;

use Exception;
use Illuminate\Support\Facades\Log;
use VK\Client\Enums\VKLanguage;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiAccessGroupsException;
use VK\Exceptions\Api\VKApiCaptchaException;
use VK\Exceptions\Api\VKApiWallAddPostException;
use VK\Exceptions\Api\VKApiWallAdsPostLimitReachedException;
use VK\Exceptions\Api\VKApiWallAdsPublishedException;
use VK\Exceptions\Api\VKApiWallDonutException;
use VK\Exceptions\Api\VKApiWallLinksForbiddenException;
use VK\Exceptions\Api\VKApiWallTooManyRecipientsException;
use VK\Exceptions\VKApiException;
use VK\Exceptions\VKClientException;

class VkApiService
{
    protected string $token;
    protected VKApiClient $client;

    public function setToken(string $token): void
    {
        $this->token = $token;
        $this->client = new VKApiClient(config('vk.version', '5.199'), VKLanguage::RUSSIAN);
    }

    /**
     * @throws VKApiWallTooManyRecipientsException
     * @throws VKApiWallAdsPublishedException
     * @throws VKApiWallAdsPostLimitReachedException
     * @throws VKApiWallLinksForbiddenException
     * @throws VKClientException
     * @throws VKApiWallAddPostException
     * @throws VKApiException
     * @throws VKApiWallDonutException
     */
    public function wallPost(int $ownerId, string $message, array $images = []): array
    {
        $attachments = [];
        $files = [];

        if (!empty($images)) {
            $images = array_slice($images, 0, 10);
            $address = $this->client->photos()->getWallUploadServer($this->token);

            foreach ($images as $image) {
                try {
                    $filename = downloadFile($image, storage_path('app/tmp'));
                    $files[] = $filename;

                    $photo = $this->client->getRequest()->upload($address['upload_url'], 'photo', $filename);
                    $saveResponse = $this->client->photos()->saveWallPhoto($this->token, [
                        'server' => $photo['server'],
                        'photo' => $photo['photo'],
                        'hash' => $photo['hash'],
                        'user_id' => $ownerId,
                    ])[0];

                    $attachments[] = 'photo' . $saveResponse['owner_id'] . '_' . $saveResponse['id'];
                } catch (\Throwable $th) {
                    Log::channel('vk')->error($th->getMessage());
                    continue;
                }
            }
        }

        $result = $this->client->wall()->post($this->token, [
            'owner_id' => $ownerId,
            'message' => $message,
            'attachments' => $attachments,
        ]);

        foreach ($files as $file) {
            unlink($file);
        }

        return $result;
    }

    public function storiesPost(string $postId, string $imagePath): array
    {
        $address = $this->client->stories()->getPhotoUploadServer($this->token, [
            'add_to_news' => 1,
            'link_url' => 'https://vk.com/wall' . $postId,
            'link_text' => 'Смотреть',
        ]);

        $upload = $this->client->getRequest()->upload($address['upload_url'], 'photo', $imagePath);

        return $this->client->stories()->save($this->token, [
            'upload_results' => $upload['upload_result'],
        ]);
    }

    /**
     * @throws VKClientException
     * @throws VKApiException
     * @throws VKApiAccessGroupsException
     * @throws Exception
     */
    public function createReposts(int $userId, string $postId, array $groupIds): array
    {
        $userGroupsResponse = $this->client->groups()->get($this->token, [
            'user_id' => $userId,
            'filter' => 'moder',
        ]);

        $userGroups = array_filter($userGroupsResponse['items'], fn ($groupId) => in_array($groupId, $groupIds));

        if (count($userGroups) < 1) {
            throw new Exception('Не найдено групп, в которые пользователь имеет право постить');
        }

        $groupIdsJson = json_encode(array_values($userGroups));
        $postId = 'wall' . $postId;

        $code = <<<VKSCRIPT
        var groups = {$groupIdsJson};
        var postId = "{$postId}";
        var result = [];
        var i = 0;
        while (i < groups.length && i < 25) {
            var groupId = groups[i];
            var response = API.wall.repost({
                "object": postId,
                "group_id": groupId
            });
            result.push({
                "group_id": groupId,
                "response": response
            });
            i = i + 1;
        }
        return result;
VKSCRIPT;

        return $this->client->getRequest()->post('execute', $this->token, [
            'code' => $code,
        ]);
    }

    public function getPostsStats(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        return $this->client->wall()->getById($this->token, [
            'posts' => implode(',', $postIds),
        ]);
    }

    public function checkToken(): bool
    {
        try {
            $this->client->users()->get($this->token, [
                'user_id' => 1,
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @throws VKApiException
     * @throws VKClientException
     */
    public function sendTokensMessage(string $text): void
    {
        $token = config('vk.notify_group_token');
        $peerIds = config('vk.notify_peer_id');

        if ($token === '' || $peerIds === '') {
            throw new Exception('VK notify credentials not configured');
        }

        $this->setToken($token);

        $this->client->messages()->send($this->token, [
            'peer_ids' => $peerIds,
            'message' => $text,
            'random_id' => time(),
            'dont_parse_links' => 1,
        ]);
    }

    public function getClient(): VKApiClient
    {
        return $this->client;
    }
}
