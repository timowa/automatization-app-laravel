<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vk\Stories;

use App\Enums\Category;
use App\Enums\Deal;
use App\Exceptions\StoryPublicationSkippedException;
use App\Services\Vk\Stories\Templates\SaleStoriesTemplate;
use App\Services\Vk\Stories\VkStoriesContext;
use App\Services\Vk\Stories\VkStoriesGenerator;
use Tests\TestCase;

class VkStoriesGeneratorTest extends TestCase
{
    public function test_generation_without_photo_skips_and_keeps_original_reason(): void
    {
        $generator = new VkStoriesGenerator;

        try {
            $generator->generate($this->context(''), new SaleStoriesTemplate);
            $this->fail('Ожидался пропуск истории без фото');
        } catch (StoryPublicationSkippedException $e) {
            $this->assertStringContainsString('нет фото', $e->getMessage());
            $this->assertStringNotContainsString('Undefined variable', $e->getMessage());
        }

        try {
            $generator->generate(
                $this->context(storage_path('app/tmp/missing-story-photo.jpg')),
                new SaleStoriesTemplate
            );
            $this->fail('Ожидался пропуск истории с недоступным фото');
        } catch (StoryPublicationSkippedException $e) {
            $this->assertStringContainsString('недоступна', $e->getMessage());
            $this->assertStringNotContainsString('Undefined variable', $e->getMessage());
        }
    }

    private function context(string $image): VkStoriesContext
    {
        return new VkStoriesContext(
            agentName: 'Агент',
            price: 100000,
            commission: null,
            deposit: null,
            address: 'Кызыл, ул. Ленина, 1',
            rooms: 1,
            image: $image,
            deal: Deal::SALE,
            category: Category::APARTMENT,
            postId: '1_1',
            offerId: 1,
        );
    }
}
