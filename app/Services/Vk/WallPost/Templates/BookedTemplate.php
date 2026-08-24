<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\VkPostContext;

class BookedTemplate implements VkPostTemplateInterface
{
    public function generate(VkPostContext $context): string
    {
        $rooms = ($roomsValue = $context->getRooms()) !== null ? "Комнаты: {$roomsValue}" : '';
        $livingArea = ($livingValue = $context->getLivingArea()) !== null ? "Жилая площадь: {$livingValue}" : '';
        $kitchenArea = ($kitchenValue = $context->getKitchenArea()) !== null ? "Площадь кухни: {$kitchenValue}" : '';
        $area = ($areaValue = $context->getArea()) !== null ? "Общая площадь: {$areaValue}" : '';
        $floorLine = $context->getFloorLine() ?? '';

        $details = array_filter([$rooms, $livingArea, $kitchenArea, $area, $floorLine]);
        $detailsBlock = $details !== [] ? "\n" . implode("\n", $details) : '';

        $title = mb_strtoupper($context->getCategory());

        return <<<TEXT
            БРОНИРОВАНИЕ {$title} в г. {$context->cityName}!

            Адрес: {$context->address}{$detailsBlock}

            {$context->getPrice()} руб.
            Статус: забронировано

            КОНТАКТЫ:

            Агентство:  Брокер Плюс
            Агент собственника: {$context->agentName}
            📞 Звоните: {$context->getAgentPhone()}

            #{$context->getHasTag()}
        TEXT;
    }
}
