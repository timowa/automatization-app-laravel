<?php

declare(strict_types=1);

namespace Tests\Unit\Services\VK\WallPost;

use App\Enums\Category;
use App\Enums\Deal;
use App\Services\Vk\WallPost\VkPostContext;
use PHPUnit\Framework\TestCase;

class VkPostContextTest extends TestCase
{
    private function context(array $params = []): VkPostContext
    {
        return new VkPostContext(
            address: $params['address'] ?? 'ул. Калинина, 24',
            rooms: $params['rooms'] ?? 2,
            area: $params['area'] ?? 51.6,
            livingArea: $params['livingArea'] ?? null,
            kitchenArea: $params['kitchenArea'] ?? null,
            floor: $params['floor'] ?? null,
            floorsTotal: $params['floorsTotal'] ?? null,
            price: $params['price'] ?? 695000,
            oldPrice: $params['oldPrice'] ?? null,
            commission: $params['commission'] ?? null,
            deposit: $params['deposit'] ?? null,
            agentName: $params['agentName'] ?? 'Агент',
            agentPhone: $params['agentPhone'] ?? '+79999999999',
            cityName: $params['cityName'] ?? 'Кызыл',
            images: $params['images'] ?? [],
            offerId: $params['offerId'] ?? 1,
            deal: $params['deal'] ?? Deal::SALE,
            category: $params['category'] ?? Category::APARTMENT,
            code: $params['code'] ?? '217-100'
        );
    }

    public function test_get_floor_line_returns_floor_with_total(): void
    {
        $context = $this->context(['floor' => 3, 'floorsTotal' => 5]);

        $this->assertSame('Этаж: 3 / 5', $context->getFloorLine());
    }

    public function test_get_floor_line_returns_floor_only_when_total_missing(): void
    {
        $context = $this->context(['floor' => 3, 'floorsTotal' => null]);

        $this->assertSame('Этаж: 3', $context->getFloorLine());
    }

    public function test_get_floor_line_returns_null_when_floor_missing(): void
    {
        $context = $this->context(['floor' => null, 'floorsTotal' => 5]);

        $this->assertNull($context->getFloorLine());
    }

    public function test_get_floor_line_returns_null_when_floor_and_total_missing(): void
    {
        $context = $this->context(['floor' => null, 'floorsTotal' => null]);

        $this->assertNull($context->getFloorLine());
    }

    public function test_get_living_area_returns_formatted_value_when_positive(): void
    {
        $context = $this->context(['livingArea' => 35.0]);

        $this->assertSame(formatArea(35.0), $context->getLivingArea());
    }

    public function test_get_living_area_returns_null_when_zero(): void
    {
        $context = $this->context(['livingArea' => 0.0]);

        $this->assertNull($context->getLivingArea());
    }

    public function test_get_living_area_returns_null_when_null(): void
    {
        $context = $this->context(['livingArea' => null]);

        $this->assertNull($context->getLivingArea());
    }
}
