<?php

declare(strict_types=1);

namespace App\Services\Vk;

use Exception;
use GuzzleHttp\Client;
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
            'close_comments' => 0
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

    /**
     * @throws VKClientException
     * @throws VKApiException
     */
    public function createComment(int $ownerId, int $postId, string $message): array
    {
        return $this->client->wall()->createComment($this->token, [
            'owner_id' => $ownerId,
            'post_id' => $postId,
            'message' => $message,
        ]);
    }

    private function getMarketUploadServer(int $groupId): string
    {
        $address = $this->client->getRequest()->post('market.getProductPhotoUploadServer', $this->token, [
            'group_id' => $groupId,
            'bulk' => true
        ]);
        return $address['upload_url'];
    }

    public function getGroupsWithMarketForUser(int $userId): array
    {
        $code = <<<VKSCRIPT
            var userId = {$userId};
            var filter = 'admin,editor,moder';
            var groupIds = [];
            var groups = API.groups.get({
                "user_id": userId,
                "filter": filter,
                "extended": 1,
                "fields": 'market'
            });
            var i = 0;
            while (i < groups.items.length) {
                var group = groups.items[i];
                if (group.market.enabled == 1) {
                    groupIds.push(group.id);
                }
                i = i + 1;
            }

        return groupIds;
        VKSCRIPT;

        return $this->client->getRequest()->post('execute', $this->token, [
            'code' => $code,
        ]);

    }

    /**
     * @throws VKClientException
     * @throws VKApiException
     */
    public function uploadMarketPhoto(int $groupId, array $imagePaths): array
    {
        $attachments = [];
        $files = [];

        if (empty($imagePaths)) {
            return $attachments;
        }

        $imagePaths = array_slice($imagePaths, 0, 5);
        $address = $this->getMarketUploadServer($groupId);

        $i = 0;
        $multipart = [];
        foreach ($imagePaths as $image) {
            try {
                $i++;
                $filename = is_file($image)
                    ? $image
                    : downloadFile($image, storage_path('app/tmp'));

                if (is_file($image) === false) {
                    $files[] = $filename;
                }
                $multipart[] = [
                    'name' => $i === 0 ? 'file' : "file{$i}",
                    'contents' => fopen($filename, 'rb')
                ];

            } catch (\Throwable $th) {
                Log::channel('vk')->error('Ошибка при получении изображения: ' . $th->getMessage(), ['trace' => $th->getTraceAsString(), 'file' => $th->getFile(), 'line' => $th->getLine()]);
                continue;
            }
        }
        try {
            $client = new Client();
            $response = $client->post($address, [
                'multipart' => $multipart
            ]);
            $res = $response->getBody()->getContents();

            $saveResponse = $client->post('https://api.vk.com/method/market.saveProductPhotoBulk', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'multipart' => [
                    [
                        'name' => 'upload_response',
                        'contents' => $res
                    ],
                    [
                        'name' => 'v',
                        'contents' => '5.199'
                    ]
                ]
            ]);

            $res = json_decode(
                $saveResponse->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR);

            $attachments = array_column($res['response'], 'photo_id');

        } catch (\Throwable $th) {
            Log::channel('vk')->error('Ошибка при загрузке изображения: ' . $th->getMessage(), ['trace' => $th->getTraceAsString(), 'file' => $th->getFile(), 'line' => $th->getLine()]);
        }


        foreach ($files as $file) {
            unlink($file);
        }

        return $attachments;
    }

    /**
     * @throws VKClientException
     * @throws VKApiException
     */
    public function createProductsBatch(int $groupId, string $name, string $description, int $price, int $categoryId, array $photoIds): array
    {
        $mainPhotoId = $photoIds[0] ?? null;
        $photoIdsStr = implode(',', array_slice($photoIds, 1));
        $res = $this->client->market()->add($this->token, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'category_id' => $categoryId,
            'photo' => $mainPhotoId,
            'main_photo_id' => $mainPhotoId,
            'photo_ids' => $photoIdsStr,
            'owner_id' => '-' . $groupId,
        ]);

        Log::channel('vk')->error('res', ['res' => $res, 'params' => [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'category_id' => $categoryId,
            'photo' => $mainPhotoId,
            'main_photo_id' => $photoIdsStr,
            'photo_ids' => $photoIdsStr,
            'owner_id' => '-' . $groupId,
        ]]);

        return $res;

    }

    /**
     * @throws VKClientException
     * @throws VKApiException
     */
    public function editProductsBatch(array $products, string $name, string $description, int $price, int $categoryId): array
    {
        $productsJson = json_encode(array_values(array_slice($products, 0, 25)));

        $code = <<<VKSCRIPT
        var products = {$productsJson};
        var name = "{$name}";
        var description = "{$description}";
        var price = {$price};
        var categoryId = {$categoryId};
        var result = [];
        var i = 0;
        while (i < products.length && i < 25) {
            var response = API.market.edit({
                "owner_id": -products[i].group_id,
                "item_id": products[i].product_id,
                "name": name,
                "description": description,
                "category_id": categoryId,
                "price": price
            });
            result.push({
                "group_id": products[i].group_id,
                "product_id": products[i].product_id,
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

    /**
     * @throws VKClientException
     * @throws VKApiException
     */
    public function archiveProductsBatch(array $products): array
    {
        $productsJson = json_encode(array_values(array_slice($products, 0, 25)));

        $code = <<<VKSCRIPT
        var products = {$productsJson};
        var result = [];
        var i = 0;
        while (i < products.length && i < 25) {
            var response = API.market.delete({
                "owner_id": -products[i].group_id,
                "item_id": products[i].product_id
            });
            result.push({
                "group_id": products[i].group_id,
                "product_id": products[i].product_id,
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
}
