<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\ScenarioType;
use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\Templates\AnnouncementTemplate;
use App\Services\Vk\WallPost\Templates\BookedTemplate;
use App\Services\Vk\WallPost\Templates\FeedbackTemplate;
use App\Services\Vk\WallPost\Templates\PriceChangedTemplate;
use App\Services\Vk\WallPost\Templates\RentTemplate;
use App\Services\Vk\WallPost\Templates\SaleTemplate;
use App\Services\Vk\WallPost\Templates\SoldTemplate;

final class ScenarioVkPostTemplateResolver
{
    public function resolve(ScenarioType $scenarioType): VkPostTemplateInterface
    {
        return match ($scenarioType) {
            ScenarioType::ANNOUNCEMENT => new AnnouncementTemplate,
            ScenarioType::RENT => new RentTemplate,
            ScenarioType::SALE => new SaleTemplate,
            ScenarioType::AGENT_CHANGED => new SaleTemplate,
            ScenarioType::PRICE_CHANGED => new PriceChangedTemplate,
            ScenarioType::BOOKING => new BookedTemplate,
            ScenarioType::SOLD => new SoldTemplate,
            ScenarioType::FEEDBACK => new FeedbackTemplate,
            default => throw new \Exception('Неподдерживаемый сценарий для шаблона поста'),
        };
    }
}
