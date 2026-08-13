<?php

declare(strict_types=1);

namespace App\Services\Vk;

class FakeVkApiService extends VkApiService
{
    public array $calls = [];
    public bool $failNext = false;
    public string $failWith = 'VKApiException';
    public ?string $failMessage = 'VK API error';

    public function setFailNext(string $exceptionClass, string $message = 'VK API error'): void
    {
        $this->failNext = true;
        $this->failWith = $exceptionClass;
        $this->failMessage = $message;
    }

    public function reset(): void
    {
        $this->failNext = false;
        $this->failWith = 'VKApiException';
        $this->failMessage = 'VK API error';
        $this->calls = [];
    }

    private function maybeFail(string $method): void
    {
        if (!$this->failNext) {
            return;
        }

        $this->failNext = false;
        $class = 'VK\\Exceptions\\' . $this->failWith;
        if (class_exists($class)) {
            throw new $class($this->failMessage);
        }

        throw new \RuntimeException($this->failMessage);
    }

    public function wallPost(int $ownerId, string $message, array $images = []): array
    {
        $this->calls[] = ['method' => 'wallPost', 'owner_id' => $ownerId];
        $this->maybeFail('wallPost');

        return ['post_id' => fake()->unique()->numberBetween(1, 1_000_000)];
    }

    public function storiesPost(string $postId, string $imagePath): array
    {
        $this->calls[] = ['method' => 'storiesPost', 'post_id' => $postId];
        $this->maybeFail('storiesPost');

        return ['count' => 1];
    }

    public function createReposts(int $userId, string $postId, array $groupIds): array
    {
        $this->calls[] = ['method' => 'createReposts', 'post_id' => $postId];
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

    public function createProduct(int $groupId, string $name, string $description, int $price, int $categoryId, array $imagePaths): array
    {
        $this->calls[] = ['method' => 'createProduct', 'group_id' => $groupId];
        $this->maybeFail('createProduct');

        return ['market_item_id' => fake()->unique()->numberBetween(1, 1_000_000)];
    }

    public function editProduct(int $groupId, int $productId, string $name, string $description, int $price, int $categoryId): array
    {
        $this->calls[] = ['method' => 'editProduct', 'group_id' => $groupId, 'product_id' => $productId];
        $this->maybeFail('editProduct');

        return ['success' => 1];
    }

    public function archiveProduct(int $groupId, int $productId): array
    {
        $this->calls[] = ['method' => 'archiveProduct', 'group_id' => $groupId, 'product_id' => $productId];
        $this->maybeFail('archiveProduct');

        return ['success' => 1];
    }
}
