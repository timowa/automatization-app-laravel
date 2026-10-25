<?php

declare(strict_types=1);

namespace App\Services\Vk;

use App\Exceptions\VkStoryNotSavedException;
use App\Helpers\ImageWatermarker;
use App\Helpers\VkApiErrorInspector;
use App\Logging\SensitiveDataRedactor;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use VK\Client\Enums\VKLanguage;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiAccessGroupsException;
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
    public const NO_REPOST_RIGHTS_MESSAGE = 'Не найдено групп, в которые пользователь имеет право постить';

    public const HTTP_TIMEOUT_SECONDS = 30;

    public const WELCOME_BATCH_LIMIT = 25;

    public const FRIEND_FIELDS = [
        'bdate',
        'city',
        'contacts',
        'country',
        'domain',
        'education',
        'has_mobile',
        'timezone',
        'last_seen',
        'nickname',
        'online',
        'photo_100',
        'photo_200_orig',
        'photo_50',
        'photo_id',
        'relation',
        'relation_partner',
        'sex',
        'status',
        'universities',
        'deactivated',
        'is_closed',
        'home_town',
        'has_photo',
        'occupation',
        'schools',
        'lists',
        'maiden_name',
        'career',
        'site',
        'verified',
        'blacklisted_by_me',
        'followers_count',
    ];

    protected string $token;

    protected VKApiClient $client;

    public function setToken(string $token): void
    {
        $this->token = $token;
        $this->client = new VKApiClient(config('vk.version', '5.199'), VKLanguage::RUSSIAN);
        $this->extendHttpTimeout($this->client);
    }

    private function extendHttpTimeout(VKApiClient $client): void
    {
        $guard = new VkApiResponseGuard(new SensitiveDataRedactor);
        $stack = HandlerStack::create();
        $stack->unshift(Middleware::mapResponse(
            static fn (ResponseInterface $response): ResponseInterface => $guard->sanitize($response),
        ));

        $request = $client->getRequest();
        $property = new \ReflectionProperty($request, 'client');
        $property->setValue($request, new Client([
            'base_uri' => 'https://api.vk.com/method',
            'timeout' => self::HTTP_TIMEOUT_SECONDS,
            'connect_timeout' => 10,
            'http_errors' => false,
            'handler' => $stack,
        ]));
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
    public function wallPost(int $ownerId, string $message, array $attachments = []): array
    {
        $result = $this->client->wall()->post($this->token, [
            'owner_id' => $ownerId,
            'message' => $message,
            'attachments' => $attachments,
            'close_comments' => 0,
            'primary_attachments_mode' => 'grid',
        ]);

        return $result;
    }

    /**
     * Загружает изображение на сервер ВК для поста на стене.
     *
     * @return int|null media_id (id фото на сервере ВК) или null при ошибке
     */
    public function uploadWallPhoto(string $imageUrl, int $ownerId, string $caption = ''): ?int
    {
        $filename = null;
        $imageWatermarker = new ImageWatermarker;
        try {
            $filename = downloadFile($imageUrl, storage_path('app/tmp'));
            $imageInfo = @getimagesize($filename);
            Log::channel('vk-images')->info('Файл изображения скачан через CURL', ['file_name' => $filename, 'image_info' => $imageInfo]);
            $filename = $imageWatermarker->apply($filename);

            $address = $this->client->photos()->getWallUploadServer($this->token);
            Log::channel('vk-images')->info('Получен адрес для загрузки изображения', ['address' => $address]);

            $photo = $this->client->getRequest()->upload($address['upload_url'], 'photo', $filename);
            Log::channel('vk-images')->info('Ответ вк по загрузке изображения', ['response' => $photo, 'params' => [
                'upload_url' => $address['upload_url'],
                'parameter_name' => 'photo',
                'path' => $filename,
            ]]);

            if (empty($photo['photo'])) {
                Log::channel('vk-images')->warning('Не удалось загрузить файл скачанный через CURL. Пробуем сохранить через GD');

                $filename = downloadFileGD($imageUrl, storage_path('app/tmp'));
                $imageInfo = @getimagesize($filename);
                Log::channel('vk-images')->info('Файл изображения скачан через CURL и сохранен через GD', ['file_name' => $filename, 'image_info' => $imageInfo]);
                $filename = $imageWatermarker->apply($filename);

                $address = $this->client->photos()->getWallUploadServer($this->token);
                Log::channel('vk-images')->info('Получен адрес для загрузки изображения', ['address' => $address]);

                $photo = $this->client->getRequest()->upload($address['upload_url'], 'photo', $filename);
                Log::channel('vk-images')->info('Ответ вк по загрузке изображения', ['response' => $photo, 'params' => [
                    'upload_url' => $address['upload_url'],
                    'parameter_name' => 'photo',
                    'path' => $filename,
                ]]);

            }

            if (empty($photo['photo'])) {
                throw new \RuntimeException('Не удалось загрузить изображение скачанное через CURL и GD');
            }

            $saveResponse = $this->client->photos()->saveWallPhoto($this->token, [
                'server' => $photo['server'],
                'photo' => $photo['photo'],
                'hash' => $photo['hash'],
                'user_id' => $ownerId,
                'caption' => $caption,
            ]);

            Log::channel('vk-images')->info('Ответ вк по сохранению изображения', ['response' => $saveResponse, 'params' => [
                'server' => $photo['server'],
                'photo' => $photo['photo'],
                'hash' => $photo['hash'],
                'user_id' => $ownerId,
                'caption' => $caption,
            ]]);

            return (int) $saveResponse[0]['id'];
        } catch (\Throwable $th) {
            Log::channel('vk-images')->error('Ошибка при загрузке фотографии на сервер Вконтакте: '.$th->getMessage(), [
                'image_original_url' => $imageUrl,
                'image_local_name' => $filename,
            ]);

            if (VkApiErrorInspector::isFlood($th)) {
                throw $th;
            }

            return null;
        } finally {
            if ($filename !== null && is_file($filename)) {
                @unlink($filename);
            }
        }
    }

    public function storiesPost(string $postId, string $imagePath): array
    {
        $address = $this->client->stories()->getPhotoUploadServer($this->token, [
            'add_to_news' => 1,
            'link_url' => 'https://vk.com/wall'.$postId,
            'link_text' => 'Смотреть',
        ]);

        $upload = $this->client->getRequest()->upload($address['upload_url'], 'photo', $imagePath);

        $saved = $this->client->stories()->save($this->token, [
            'upload_results' => is_array($upload) ? ($upload['upload_result'] ?? null) : null,
        ]);

        if (! self::isStorySaveSuccessful($saved)) {
            $uploadSafe = $this->redactSecrets(is_array($upload) ? $upload : ['response' => $upload]);

            throw new VkStoryNotSavedException(
                'ВКонтакте не сохранил историю (count = 0). Ответ загрузки: '
                .json_encode($uploadSafe, JSON_UNESCAPED_UNICODE)
            );
        }

        return $saved;
    }

    /**
     * VK иногда отдаёт items без count — если items не пустой, история уже создана.
     *
     * @param  array<string, mixed>  $saved
     */
    public static function isStorySaveSuccessful(array $saved): bool
    {
        if ((int) ($saved['count'] ?? 0) >= 1) {
            return true;
        }

        $items = $saved['items'] ?? null;

        return is_array($items) && $items !== [];
    }

    /**
     * Пост существует во ВКонтакте и не помечен удалённым.
     */
    public function wallPostExists(string $fullId): bool
    {
        $response = $this->client->wall()->getById($this->token, [
            'posts' => $fullId,
        ]);

        $posts = [];
        if (isset($response['items']) && is_array($response['items'])) {
            $posts = $response['items'];
        } elseif (is_array($response) && array_is_list($response)) {
            $posts = $response;
        }

        if ($posts === []) {
            return false;
        }

        $post = $posts[0] ?? null;
        if (! is_array($post) || ! isset($post['id'])) {
            return false;
        }

        return ($post['is_deleted'] ?? false) !== true;
    }

    private function redactSecrets(mixed $value): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/token|secret/i', $key) === 1) {
                    continue;
                }

                $clean[$key] = $this->redactSecrets($item);
            }

            return $clean;
        }

        if (! is_string($value)) {
            return $value;
        }

        $token = $this->token ?? '';
        if ($token !== '' && str_contains($value, $token)) {
            $value = str_replace($token, '[hidden]', $value);
        }

        return preg_replace('/((?:access_)?token)=([^&\s]+)/i', '$1=[hidden]', $value) ?? $value;
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
            'filter' => 'admin,editor,moder',
        ]);

        $userGroups = self::groupIdsUserCanPost($userGroupsResponse['items'] ?? [], $groupIds);

        if (count($userGroups) < 1) {
            throw new Exception(self::NO_REPOST_RIGHTS_MESSAGE);
        }

        $groupIdsJson = json_encode(array_values($userGroups));
        $postId = 'wall'.$postId;

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

    /**
     * @param  list<int|string|array<string, mixed>>  $items
     * @param  list<int|string>  $groupIds
     * @return list<int>
     */
    public static function groupIdsUserCanPost(array $items, array $groupIds): array
    {
        $allowed = array_map(intval(...), $groupIds);
        $matched = [];

        foreach ($items as $item) {
            $id = is_array($item) ? (int) ($item['id'] ?? 0) : (int) $item;
            if ($id > 0 && in_array($id, $allowed, true)) {
                $matched[] = $id;
            }
        }

        return array_values(array_unique($matched));
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
    public function sendTechMessage(string $text): void
    {
        $token = config('vk.notify_group_token');
        $peerIds = config('vk.notify_peer_ids');

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

    /**
     * @throws VKApiException
     * @throws VKClientException
     */
    public function getFriends(int $userId): array
    {
        $friends = [];
        $offset = 0;
        $count = 5000;

        do {
            $response = $this->client->friends()->get($this->token, [
                'user_id' => $userId,
                'fields' => self::FRIEND_FIELDS,
                'count' => $count,
                'offset' => $offset,
            ]);

            $items = $response['items'] ?? [];
            $friends = array_merge($friends, $items);
            $offset += count($items);

            if ($offset < (int) ($response['count'] ?? 0)) {
                usleep(350_000);
            }
        } while ($items !== [] && $offset < (int) ($response['count'] ?? 0));

        return $friends;
    }

    /**
     * @return array<int, string>
     *
     * @throws VKApiException
     * @throws VKClientException
     */
    public function getFriendLists(): array
    {
        $response = $this->client->friends()->getLists($this->token, []);
        $names = [];

        foreach ($response['items'] ?? [] as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $id = (int) $item['id'];
            if ($id <= 0) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $names[$id] = $name !== '' ? $name : (string) $id;
        }

        return $names;
    }

    /**
     * @throws VKApiException
     * @throws VKClientException
     */
    public function sendDirectMessage(int $userId, string $message, ?string $attachment = null): mixed
    {
        $params = [
            'peer_id' => $userId,
            'message' => $message,
            'random_id' => random_int(1, 2_147_483_647),
            'dont_parse_links' => 1,
        ];

        if ($attachment !== null && $attachment !== '') {
            $params['attachment'] = $attachment;
        }

        return $this->client->messages()->send($this->token, $params);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws VKApiException
     * @throws VKClientException
     */
    public function getFriendRequests(): array
    {
        $items = [];
        $offset = 0;
        $count = 1000;

        do {
            $response = $this->client->friends()->getRequests($this->token, [
                'count' => $count,
                'offset' => $offset,
                'extended' => 1,
                'need_mutual' => 1,
                'out' => 0,
                'need_viewed' => 1,
            ]);

            $batch = $response['items'] ?? [];
            if (! is_array($batch)) {
                break;
            }

            foreach ($batch as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            $offset += count($batch);

            if ($offset < (int) ($response['count'] ?? 0) && $batch !== []) {
                usleep(350_000);
            }
        } while ($batch !== [] && $offset < (int) ($response['count'] ?? 0));

        return $items;
    }

    /**
     * @throws VKApiException
     * @throws VKClientException
     */
    public function hasOutgoingMessageWithUser(int $userId): bool
    {
        $response = $this->client->messages()->getHistory($this->token, [
            'user_id' => $userId,
            'count' => 20,
            'rev' => 0,
        ]);

        $items = $response['items'] ?? [];
        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((int) ($item['out'] ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * До 25 пользователей одним execute: у кого уже есть исходящее сообщение.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     *
     * @throws VKApiException
     * @throws VKClientException
     */
    public function findUsersWithOutgoingMessages(array $userIds): array
    {
        $peers = [];
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0) {
                $peers[$userId] = $userId;
            }
        }
        $peers = array_slice(array_values($peers), 0, self::WELCOME_BATCH_LIMIT);
        if ($peers === []) {
            return [];
        }

        $rows = $this->client->getRequest()->post('execute', $this->token, [
            'code' => self::outgoingHistoryExecuteCode($peers),
        ]);
        if (! is_array($rows)) {
            return [];
        }

        return self::userIdsWithOutgoingMessages($peers, $rows);
    }

    /**
     * До 25 вызовов messages.getHistory без условий: в VKScript if принимает только boolean.
     *
     * @param  list<int>  $userIds
     */
    public static function outgoingHistoryExecuteCode(array $userIds): string
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = 'API.messages.getHistory({"user_id":'.(int) $userId.',"count":20})';
        }

        return 'return ['.implode(',', $calls).'];';
    }

    /**
     * @param  list<int>  $userIds
     * @param  list<mixed>  $rows
     * @return list<int>
     */
    public static function userIdsWithOutgoingMessages(array $userIds, array $rows): array
    {
        $outgoing = [];
        foreach (array_values($userIds) as $index => $userId) {
            $row = $rows[$index] ?? null;
            if (! is_array($row) || ! is_array($row['items'] ?? null)) {
                continue;
            }

            foreach ($row['items'] as $item) {
                if (is_array($item) && (int) ($item['out'] ?? 0) === 1) {
                    $outgoing[] = (int) $userId;
                    break;
                }
            }
        }

        return array_values(array_filter($outgoing, fn (int $id): bool => $id > 0));
    }

    /**
     * До 25 личных сообщений одним execute. Отказ одного пользователя не отменяет остальных.
     *
     * @param  list<array{user_id:int, message:string, attachment?:string|null}>  $messages
     * @return list<array{user_id:int, message_id:int, error:?string, error_code:int}>
     *
     * @throws VKApiException
     * @throws VKClientException
     * @throws \JsonException
     */
    public function sendDirectMessages(array $messages): array
    {
        $payload = [];
        foreach (array_slice($messages, 0, self::WELCOME_BATCH_LIMIT) as $message) {
            $item = [
                'user_id' => (int) $message['user_id'],
                'message' => (string) $message['message'],
                'random_id' => random_int(1, 2_147_483_647),
            ];
            $attachment = $message['attachment'] ?? null;
            if (is_string($attachment) && $attachment !== '') {
                $item['attachment'] = $attachment;
            }
            if ($item['user_id'] > 0 && $item['message'] !== '') {
                $payload[] = $item;
            }
        }
        if ($payload === []) {
            return [];
        }

        $rows = $this->client->getRequest()->post('execute', $this->token, [
            'code' => self::directMessagesExecuteCode($payload),
        ]);

        return self::mapDirectMessageResults($payload, [
            'response' => $rows,
        ]);
    }

    /**
     * До 25 вызовов messages.send. Вложение добавляется в PHP, чтобы в VKScript не было if по строке.
     *
     * @param  list<array{user_id:int, message:string, random_id:int, attachment?:string}>  $payload
     */
    public static function directMessagesExecuteCode(array $payload): string
    {
        $calls = [];
        foreach ($payload as $item) {
            $params = [
                'peer_id' => (int) $item['user_id'],
                'message' => (string) $item['message'],
                'random_id' => (int) $item['random_id'],
                'dont_parse_links' => 1,
            ];
            $attachment = $item['attachment'] ?? null;
            if (is_string($attachment) && $attachment !== '') {
                $params['attachment'] = $attachment;
            }
            $calls[] = 'API.messages.send('.json_encode(
                $params,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).')';
        }

        return 'return ['.implode(',', $calls).'];';
    }

    /**
     * @param  list<array{user_id:int, message:string, random_id:int, attachment?:string}>  $payload
     * @param  array<string, mixed>  $body
     * @return list<array{user_id:int, message_id:int, error:?string, error_code:int}>
     *
     * @throws VKClientException
     */
    public static function mapDirectMessageResults(array $payload, array $body): array
    {
        $rows = $body['response'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new VKClientException('execute не вернул список сообщений');
        }

        $errors = [];
        foreach (is_array($body['execute_errors'] ?? null) ? $body['execute_errors'] : [] as $error) {
            if (is_array($error)) {
                $errors[] = $error;
            }
        }

        $errorIndex = 0;
        $results = [];
        foreach ($payload as $index => $item) {
            $row = $rows[$index] ?? null;
            $response = is_array($row) ? ($row['response'] ?? null) : $row;
            $messageId = 0;
            if (is_int($response) || (is_string($response) && is_numeric($response))) {
                $messageId = (int) $response;
            } elseif (is_array($response) && isset($response['message_id'])) {
                $messageId = (int) $response['message_id'];
            }

            $error = null;
            $code = 0;
            if ($messageId <= 1) {
                $executeError = $errors[$errorIndex] ?? null;
                if (is_array($executeError)) {
                    $errorIndex++;
                    $error = (string) ($executeError['error_msg'] ?? '');
                    $code = (int) ($executeError['error_code'] ?? 0);
                }
            }

            $results[] = [
                'user_id' => (int) $item['user_id'],
                'message_id' => $messageId,
                'error' => $error,
                'error_code' => $code,
            ];
        }

        return $results;
    }

    /**
     * Загружает локальный файл как фото для вложений в личные сообщения.
     *
     * @return int media id из photos.saveMessagesPhoto
     *
     * @throws VKApiException
     * @throws VKClientException
     * @throws \RuntimeException
     */
    public function uploadMessagesPhoto(string $absolutePath): int
    {
        if (! is_file($absolutePath)) {
            Log::channel('vk-images')->error('Файл визитки не найден перед загрузкой во ВК', [
                'path' => $absolutePath,
            ]);
            throw new \RuntimeException('Файл визитки не найден');
        }

        $imageInfo = @getimagesize($absolutePath);
        Log::channel('vk-images')->info('Начало загрузки визитки во ВК', [
            'path' => $absolutePath,
            'size_bytes' => filesize($absolutePath),
            'image_info' => $imageInfo,
        ]);

        try {
            $address = $this->client->photos()->getMessagesUploadServer($this->token);
            Log::channel('vk-images')->info('Получен адрес для загрузки визитки', [
                'address' => $address,
            ]);

            $upload = $this->client->getRequest()->upload($address['upload_url'], 'photo', $absolutePath);
            Log::channel('vk-images')->info('Ответ ВК по загрузке визитки на upload-сервер', [
                'response' => $upload,
                'params' => [
                    'upload_url' => $address['upload_url'] ?? null,
                    'parameter_name' => 'photo',
                    'path' => $absolutePath,
                ],
            ]);

            if (empty($upload['photo'])) {
                Log::channel('vk-images')->error('Upload-сервер ВК вернул пустое поле photo', [
                    'path' => $absolutePath,
                    'size_bytes' => is_file($absolutePath) ? filesize($absolutePath) : null,
                    'upload_response' => $upload,
                    'upload_url' => $address['upload_url'] ?? null,
                ]);
                throw new \RuntimeException('Не удалось загрузить визитку на сервер ВКонтакте: пустой ответ photo');
            }

            $saveParams = [
                'photo' => $upload['photo'],
                'server' => $upload['server'],
                'hash' => $upload['hash'],
            ];
            $saved = $this->client->photos()->saveMessagesPhoto($this->token, $saveParams);

            Log::channel('vk-images')->info('Ответ ВК по сохранению визитки', [
                'response' => $saved,
                'params' => $saveParams,
            ]);

            $mediaId = (int) ($saved[0]['id'] ?? 0);
            if ($mediaId <= 0) {
                Log::channel('vk-images')->error('photos.saveMessagesPhoto не вернул id визитки', [
                    'path' => $absolutePath,
                    'save_response' => $saved,
                    'save_params' => $saveParams,
                ]);
                throw new \RuntimeException('ВКонтакте не вернул id визитки');
            }

            Log::channel('vk-images')->info('Визитка успешно сохранена во ВК', [
                'media_id' => $mediaId,
                'owner_id' => $saved[0]['owner_id'] ?? null,
            ]);

            return $mediaId;
        } catch (\Throwable $th) {
            Log::channel('vk-images')->error('Ошибка загрузки визитки во ВК: '.$th->getMessage(), [
                'path' => $absolutePath,
                'exception' => $th::class,
                'code' => $th->getCode(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'prev' => $th->getPrevious(),
            ]);
            throw $th;
        }
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

    /**
     * @throws VKClientException
     * @throws VKApiException
     */
    public function likePost(int $ownerId, int $postId): array
    {
        return $this->client->likes()->add($this->token, [
            'type' => 'post',
            'owner_id' => $ownerId,
            'item_id' => $postId,
        ]);
    }

    private function getMarketUploadServer(int $groupId): string
    {
        $address = $this->client->getRequest()->post('market.getProductPhotoUploadServer', $this->token, [
            'group_id' => $groupId,
            'bulk' => true,
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

                $filename = (new ImageWatermarker)->apply($filename);

                if (is_file($image) === false) {
                    $files[] = $filename;
                }
                $multipart[] = [
                    'name' => $i === 0 ? 'file' : "file{$i}",
                    'contents' => fopen($filename, 'rb'),
                ];

            } catch (\Throwable $th) {
                if (VkApiErrorInspector::isFlood($th)) {
                    throw $th;
                }

                Log::channel('vk-images')->error('Ошибка при получении изображения: '.$th->getMessage(), ['trace' => $th->getTraceAsString(), 'file' => $th->getFile(), 'line' => $th->getLine()]);

                continue;
            }
        }
        try {
            $client = new Client;
            $response = $client->post($address, [
                'multipart' => $multipart,
            ]);
            $res = $response->getBody()->getContents();

            $saveResponse = $client->post('https://api.vk.com/method/market.saveProductPhotoBulk', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                ],
                'multipart' => [
                    [
                        'name' => 'upload_response',
                        'contents' => $res,
                    ],
                    [
                        'name' => 'v',
                        'contents' => '5.199',
                    ],
                ],
            ]);

            $decoded = json_decode(
                $saveResponse->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR);

            $response = is_array($decoded) ? ($decoded['response'] ?? null) : null;
            if (! is_array($response)) {
                Log::channel('vk-images')->error('ВКонтакте не вернул фото товара', [
                    'response' => is_array($decoded) ? $this->redactSecrets($decoded) : null,
                ]);
            } else {
                $attachments = array_values(array_filter(
                    array_column($response, 'photo_id'),
                    static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0,
                ));
            }

        } catch (\Throwable $th) {
            Log::channel('vk-images')->error('Ошибка при загрузке изображения: '.$th->getMessage(), ['trace' => $th->getTraceAsString(), 'file' => $th->getFile(), 'line' => $th->getLine()]);

            if (VkApiErrorInspector::isFlood($th)) {
                throw $th;
            }
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
        if (! is_numeric($mainPhotoId) || (int) $mainPhotoId <= 0) {
            throw new \RuntimeException('Нет главного фото товара');
        }

        $photoIdsStr = implode(',', array_slice($photoIds, 1));
        $res = $this->client->market()->add($this->token, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'category_id' => $categoryId,
            'photo' => $mainPhotoId,
            'main_photo_id' => $mainPhotoId,
            'photo_ids' => $photoIdsStr,
            'owner_id' => '-'.$groupId,
        ]);

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
