<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\VkPostContext;

class SoldTemplate implements VkPostTemplateInterface
{
    public function generate(VkPostContext $context): string
    {
        $rooms = ($roomsValue = $context->getRooms()) !== null ? "Комнаты: {$roomsValue}" : '';
        $area = ($areaValue = $context->getArea()) !== null ? "Общая площадь: {$areaValue}" : '';
        $kitchenArea = ($kitchenValue = $context->getKitchenArea()) !== null ? "Площадь кухни: {$kitchenValue}" : '';
        $floorLine = $context->getFloorLine() ?? '';

        $details = array_filter([$rooms, $area, $kitchenArea, $floorLine]);
        $detailsBlock = $details !== [] ? "\n" . implode("\n", $details) : '';

        $title = mb_strtoupper($context->getCategory());

        return <<<TEXT
            ПРОДАНО {$title} в г. {$context->cityName}!

            Адрес: {$context->address}{$detailsBlock}

            {$context->getPrice()} руб.

            КОНТАКТЫ:

            Агентство:  Брокер Плюс
            Агент собственника: {$context->agentName}
            📞 Телефон: {$context->agentPhone}

            #{$context->getHasTag()}
        TEXT;
    }
}
