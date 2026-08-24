<?php

declare(strict_types=1);

namespace Tests\Unit\Services\VK\WallPost\Templates;

use App\Enums\Category;
use App\Enums\Deal;
use App\Services\Vk\WallPost\Templates\BookedTemplate;
use App\Services\Vk\WallPost\Templates\PriceChangedTemplate;
use App\Services\Vk\WallPost\Templates\RentOutTemplate;
use App\Services\Vk\WallPost\Templates\RentTemplate;
use App\Services\Vk\WallPost\Templates\SaleTemplate;
use App\Services\Vk\WallPost\Templates\SoldTemplate;
use App\Services\Vk\WallPost\VkPostContext;
use PHPUnit\Framework\TestCase;

class TemplatesAreaOrderTest extends TestCase
{
    private function context(): VkPostContext
    {
        return new VkPostContext(
            address: 'ул. Калинина, 24',
            rooms: 2,
            area: 51.6,
            livingArea: 35.0,
            kitchenArea: 10.0,
            floor: 3,
            floorsTotal: 5,
            price: 695000,
            oldPrice: 700000,
            commission: null,
            deposit: null,
            agentName: 'Агент',
            agentPhone: '+79999999999',
            cityName: 'Кызыл',
            images: [],
            offerId: 1,
            deal: Deal::SALE,
            category: Category::APARTMENT,
            code: '217-100'
        );
    }

    public function test_sale_template_outputs_areas_in_expected_order(): void
    {
        $text = (new SaleTemplate)->generate($this->context());

        $this->assertStringContainsString("Комнаты: 2\nЖилая площадь: {$this->expectedArea(35.0)}\nПлощадь кухни: {$this->expectedArea(10.0)}\nОбщая площадь: {$this->expectedArea(51.6)}\nЭтаж: 3 / 5", $text);
    }

    public function test_booked_template_outputs_areas_in_expected_order(): void
    {
        $text = (new BookedTemplate)->generate($this->context());

        $this->assertStringContainsString("Комнаты: 2\nЖилая площадь: {$this->expectedArea(35.0)}\nПлощадь кухни: {$this->expectedArea(10.0)}\nОбщая площадь: {$this->expectedArea(51.6)}\nЭтаж: 3 / 5", $text);
    }

    public function test_price_changed_template_outputs_areas_in_expected_order(): void
    {
        $text = (new PriceChangedTemplate)->generate($this->context());

        $this->assertStringContainsString("Комнаты: 2\nЖилая площадь: {$this->expectedArea(35.0)}\nПлощадь кухни: {$this->expectedArea(10.0)}\nОбщая площадь: {$this->expectedArea(51.6)}\nЭтаж: 3 / 5", $text);
    }

    public function test_rent_out_template_outputs_areas_in_expected_order(): void
    {
        $text = (new RentOutTemplate)->generate($this->context());

        $this->assertStringContainsString("Комнаты: 2\nЖилая площадь: {$this->expectedArea(35.0)}\nПлощадь кухни: {$this->expectedArea(10.0)}\nОбщая площадь: {$this->expectedArea(51.6)}\nЭтаж: 3 / 5", $text);
    }

    public function test_rent_template_outputs_areas_in_expected_order(): void
    {
        $text = (new RentTemplate)->generate($this->context());

        $this->assertStringContainsString("Комнаты: 2\nЖилая площадь: {$this->expectedArea(35.0)}\nПлощадь кухни: {$this->expectedArea(10.0)}\nОбщая площадь: {$this->expectedArea(51.6)}\nЭтаж: 3 / 5", $text);
    }

    public function test_sold_template_outputs_areas_in_expected_order(): void
    {
        $text = (new SoldTemplate)->generate($this->context());

        $this->assertStringContainsString("Комнаты: 2\nЖилая площадь: {$this->expectedArea(35.0)}\nПлощадь кухни: {$this->expectedArea(10.0)}\nОбщая площадь: {$this->expectedArea(51.6)}\nЭтаж: 3 / 5", $text);
    }

    public function test_floor_total_is_not_output_when_floor_is_null(): void
    {
        $context = new VkPostContext(
            address: 'ул. Калинина, 24',
            rooms: 2,
            area: 51.6,
            livingArea: null,
            kitchenArea: null,
            floor: null,
            floorsTotal: 5,
            price: 695000,
            oldPrice: null,
            commission: null,
            deposit: null,
            agentName: 'Агент',
            agentPhone: '+79999999999',
            cityName: 'Кызыл',
            images: [],
            offerId: 1,
            deal: Deal::SALE,
            category: Category::APARTMENT,
            code: '217-100'
        );

        $text = (new SaleTemplate)->generate($context);

        $this->assertStringNotContainsString('Этаж', $text);
    }

    private function expectedArea(float $area): string
    {
        return formatArea($area);
    }
}
