<?php

declare(strict_types=1);

namespace App\Services\Vk;

use App\Models\VkGroup;
use VK\Client\VKApiError;
use VK\Exceptions\VKApiException;

class FakeVkApiService extends VkApiService
{
    public array $calls = [];

    public bool $failNext = false;

    public string $failWith = VKApiException::class;

    public ?string $failMessage = 'VK API error';

    public int $failErrorCode = 1;

    public int $failTimes = 0;

    public array $storiesPostResponse = ['count' => 1];

    public bool $wallPostExistsResult = true;

    public ?\Throwable $wallPostExistsException = null;

    /**
     * Если задано, sendWelcomeBatch возвращает эти отчёты вместо успеха.
     *
     * @var list<array{user_id:int, message_id?:int|null, error?:string|null, error_code?:int}>|null
     */
    public ?array $welcomeSendResults = null;

    public bool $noRepostRights = false;

    public array $friendsResponse = [];

    /** @var array<int, string> */
    public array $friendListsResponse = [];

    /** @var list<array<string, mixed>> */
    public array $friendRequestsResponse = [];

    public bool $hasOutgoingMessage = false;

    public int $uploadMessagesPhotoId = 1001;

    /**
     * Если задано, uploadMarketPhoto возвращает эти наборы по порядку вызовов.
     *
     * @var list<list<int|string>>|null
     */
    public ?array $marketPhotoIdsSequence = null;

    private int $marketPhotoSequenceIndex = 0;

    public function setFailNext(string $exceptionClass, string $message = 'VK API error', int $errorCode = 1, int $times = 1): void
    {
        $this->failNext = true;
        $this->failTimes = max(1, $times);
        $this->failErrorCode = $errorCode;
        $this->failWith = 'VK\\Exceptions\\'.ltrim($exceptionClass, '\\');
        $this->failMessage = $message;
    }

    public function reset(): void
    {
        $this->failNext = false;
        $this->failWith = VKApiException::class;
        $this->failMessage = 'VK API error';
        $this->calls = [];
    }

    private function maybeFail(string $method): void
    {
        if (! $this->failNext) {
            return;
        }

        if ($this->failTimes > 1) {
            $this->failTimes--;
        } else {
            $this->failNext = false;
            $this->failTimes = 0;
        }

        if (is_a($this->failWith, VKApiException::class, true)) {
            throw new VKApiException(
                $this->failErrorCode,
                $this->failMessage,
                new VKApiError(['error_code' => $this->failErrorCode, 'error_msg' => $this->failMessage])
            );
        }

        if (class_exists($this->failWith)) {
            throw new $this->failWith($this->failMessage);
        }

        throw new \RuntimeException($this->failMessage);
    }

    public function wallPost(int $ownerId, string $message, array $attachments = []): array
    {
        $this->calls[] = ['method' => 'wallPost', 'owner_id' => $ownerId, 'message' => $message, 'attachments' => $attachments];
        $this->maybeFail('wallPost');

        return ['post_id' => fake()->unique()->numberBetween(1, 1_000_000)];
    }

    public function uploadWallPhoto(string $imageUrl, int $ownerId, string $caption = ''): ?int
    {
        $this->calls[] = ['method' => 'uploadWallPhoto', 'owner_id' => $ownerId];
        $this->maybeFail('uploadWallPhoto');

        return fake()->unique()->numberBetween(1, 1_000_000);
    }

    public function storiesPost(string $postId, string $imagePath): array
    {
        $this->calls[] = ['method' => 'storiesPost', 'post_id' => $postId];
        $this->maybeFail('storiesPost');

        return $this->storiesPostResponse;
    }

    public function wallPostExists(string $fullId): bool
    {
        $this->calls[] = ['method' => 'wallPostExists', 'post_id' => $fullId];

        if ($this->wallPostExistsException !== null) {
            throw $this->wallPostExistsException;
        }

        return $this->wallPostExistsResult;
    }

    /**
     * @param  list<array{user_id:int, message:string, attachment?:string|null}>  $messages
     * @return list<array{user_id:int, message_id:?int, error:?string, error_code:?int}>
     */
    public function sendWelcomeBatch(array $messages): array
    {
        $this->calls[] = ['method' => 'sendWelcomeBatch', 'count' => count($messages)];
        $this->maybeFail('sendWelcomeBatch');

        if ($this->welcomeSendResults !== null) {
            foreach ($messages as $message) {
                $attachment = $message['attachment'] ?? null;
                $this->calls[] = [
                    'method' => 'sendDirectMessage',
                    'user_id' => (int) $message['user_id'],
                    'message' => (string) $message['message'],
                    'attachment' => is_string($attachment) && $attachment !== '' ? $attachment : null,
                ];
            }

            return array_map(function (array $result): array {
                $messageId = (int) ($result['message_id'] ?? 0);
                $errorCode = (int) ($result['error_code'] ?? 0);

                return [
                    'user_id' => (int) $result['user_id'],
                    'message_id' => $messageId > 1 ? $messageId : null,
                    'error' => isset($result['error']) && $result['error'] !== '' ? (string) $result['error'] : null,
                    'error_code' => $errorCode > 0 ? $errorCode : null,
                ];
            }, $this->welcomeSendResults);
        }

        $results = [];
        foreach ($messages as $message) {
            if ($this->hasOutgoingMessage) {
                $results[] = [
                    'user_id' => (int) $message['user_id'],
                    'message_id' => null,
                    'error' => VkApiService::ALREADY_WROTE_ERROR,
                    'error_code' => null,
                ];

                continue;
            }

            $attachment = $message['attachment'] ?? null;
            $this->calls[] = [
                'method' => 'sendDirectMessage',
                'user_id' => (int) $message['user_id'],
                'message' => (string) $message['message'],
                'attachment' => is_string($attachment) && $attachment !== '' ? $attachment : null,
            ];
            try {
                $this->maybeFail('sendDirectMessage');
                $results[] = [
                    'user_id' => (int) $message['user_id'],
                    'message_id' => fake()->unique()->numberBetween(2, 1_000_000),
                    'error' => null,
                    'error_code' => null,
                ];
            } catch (\Throwable $e) {
                $code = $e instanceof VKApiException ? $e->getErrorCode() : 0;
                $results[] = [
                    'user_id' => (int) $message['user_id'],
                    'message_id' => null,
                    'error' => $e->getMessage(),
                    'error_code' => $code > 0 ? $code : null,
                ];
            }
        }

        return $results;
    }

    public function createReposts(int $userId, string $postId, array $groupIds): array
    {
        $this->calls[] = ['method' => 'createReposts', 'post_id' => $postId, 'args' => $groupIds];
        if ($this->noRepostRights) {
            throw new \Exception(VkApiService::NO_REPOST_RIGHTS_MESSAGE);
        }
        $this->maybeFail('createReposts');

        return array_map(fn ($groupId) => [
            'group_id' => $groupId,
            'response' => ['success' => 1],
        ], $groupIds);
    }

    public function createComment(int $ownerId, int $postId, string $message): array
    {
        $this->calls[] = ['method' => 'createComment', 'post_id' => $postId];
        $this->maybeFail('createComment');

        return ['comment_id' => fake()->unique()->numberBetween(1, 1_000_000)];
    }

    public function likePost(int $ownerId, int $postId): array
    {
        $this->calls[] = ['method' => 'likePost', 'owner_id' => $ownerId, 'post_id' => $postId];
        $this->maybeFail('likePost');

        return ['likes' => fake()->numberBetween(1, 100)];
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return array<int, string>
     */
    public function getFriendLists(): array
    {
        $this->calls[] = ['method' => 'getFriendLists'];

        return $this->friendListsResponse;
    }

    public function getFriends(int $userId): array
    {
        $this->calls[] = ['method' => 'getFriends', 'user_id' => $userId];
        $this->maybeFail('getFriends');

        return $this->friendsResponse;
    }

    public function sendDirectMessage(int $userId, string $message, ?string $attachment = null): mixed
    {
        $this->calls[] = [
            'method' => 'sendDirectMessage',
            'user_id' => $userId,
            'message' => $message,
            'attachment' => $attachment,
        ];
        $this->maybeFail('sendDirectMessage');

        return fake()->unique()->numberBetween(2, 1_000_000);
    }

    public function sendTechMessage(string $text): void
    {
        $this->calls[] = ['method' => 'sendTechMessage', 'message' => $text];
        $this->maybeFail('sendTechMessage');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFriendRequests(): array
    {
        $this->calls[] = ['method' => 'getFriendRequests'];
        $this->maybeFail('getFriendRequests');

        return $this->friendRequestsResponse;
    }

    public function hasOutgoingMessageWithUser(int $userId): bool
    {
        $this->calls[] = ['method' => 'hasOutgoingMessageWithUser', 'user_id' => $userId];
        $this->maybeFail('hasOutgoingMessageWithUser');

        return $this->hasOutgoingMessage;
    }

    public function uploadMessagesPhoto(string $absolutePath): int
    {
        $this->calls[] = ['method' => 'uploadMessagesPhoto', 'path' => $absolutePath];
        $this->maybeFail('uploadMessagesPhoto');

        return $this->uploadMessagesPhotoId;
    }

    public function getGroupsWithMarketForUser(int $userId): array
    {
        $groups = VkGroup::all();

        return $groups->pluck('group_id')->toArray();
    }

    public function uploadMarketPhoto(int $groupId, array $imagePaths): array
    {
        $this->calls[] = ['method' => 'uploadMarketPhoto', 'image_count' => count($imagePaths)];
        $this->maybeFail('uploadMarketPhoto');

        if ($this->marketPhotoIdsSequence !== null) {
            $ids = $this->marketPhotoIdsSequence[$this->marketPhotoSequenceIndex] ?? [];
            $this->marketPhotoSequenceIndex++;

            return $ids;
        }

        return [fake()->unique()->numberBetween(1, 1_000_000)];
    }

    public function createProductsBatch(int $groupId, string $name, string $description, int $price, int $categoryId, array $photoIds): array
    {
        $this->calls[] = [
            'method' => 'createProductsBatch',
            'group_id' => $groupId,
            'photo_count' => count($photoIds),
        ];
        $this->maybeFail('createProductsBatch');

        return [
            ['group_id' => $groupId, 'response' => ['market_item_id' => fake()->unique()->numberBetween(1, 1_000_000)]],
        ];
    }

    public function editProductsBatch(array $products, string $name, string $description, int $price, int $categoryId): array
    {
        $this->calls[] = ['method' => 'editProductsBatch', 'product_count' => count($products)];
        $this->maybeFail('editProductsBatch');

        return array_map(fn ($product) => [
            'group_id' => $product['group_id'],
            'product_id' => $product['product_id'],
            'response' => 1,
        ], $products);
    }

    public function archiveProductsBatch(array $products): array
    {
        $this->calls[] = ['method' => 'archiveProductsBatch', 'product_count' => count($products)];
        $this->maybeFail('archiveProductsBatch');

        return array_map(fn ($product) => [
            'group_id' => $product['group_id'],
            'product_id' => $product['product_id'],
            'response' => 1,
        ], $products);
    }
}
