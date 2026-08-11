<?php

namespace App\Helpers;

use App\Enums\ScenarioType;
use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\Templates\AnnouncementTemplate;

final class ScenarioVkPostTemplateResolver
{
    public function resolve(ScenarioType $scenarioType): VkPostTemplateInterface
    {
        return match ($scenarioType) {
            ScenarioType::ANNOUNCEMENT => new AnnouncementTemplate,
            default => throw new \Exception('Неподдерживаемый сценарий')
        };
    }
}
