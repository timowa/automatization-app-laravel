<?php

declare(strict_types=1);

namespace App\Services\Vk;

use VK\Client\VKApiError;
use VK\Exceptions\VKApiException;

class FakeVkApiService extends VkApiService
{
    public array $calls = [];
    public bool $failNext = false;
    public string $failWith = VKApiException::class;
    public ?string $failMessage = 'VK API error';
    public array $storiesPostResponse = ['count' => 1];

    public function setFailNext(string $exceptionClass, string $message = 'VK API error'): void
    {
        $this->failNext = true;
        $this->failWith = 'VK\\Exceptions\\' . ltrim($exceptionClass, '\\');
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
        if (!$this->failNext) {
            return;
        }

        $this->failNext = false;

        if (is_a($this->failWith, VKApiException::class, true)) {
            throw new VKApiException(
                1,
                $this->failMessage,
                new VKApiError(['error_code' => 1, 'error_msg' => $this->failMessage])
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

    public function createReposts(int $userId, string $postId, array $groupIds): array
    {
        $this->calls[] = ['method' => 'createReposts', 'post_id' => $postId, 'args' => $groupIds];
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

    public function getGroupsWithMarketForUser(int $userId): array
    {
        $groups = \App\Models\VkGroup::all();

        return $groups->pluck('group_id')->toArray();
    }

    public function uploadMarketPhoto(int $groupId, array $imagePaths): array
    {
        $this->calls[] = ['method' => 'uploadMarketPhoto', 'image_count' => count($imagePaths)];
        $this->maybeFail('uploadMarketPhoto');

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
