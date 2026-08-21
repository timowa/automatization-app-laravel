<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\VkPostContext;

class RentOutTemplate implements VkPostTemplateInterface
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
        $status = mb_strtoupper($context->getDeclensedStatus('Сдано'));

        return <<<TEXT
            {$status} {$title} в г. {$context->cityName}!

            Адрес: {$context->address}{$detailsBlock}

            Аренда: {$context->getPrice()} руб./мес.

            КОНТАКТЫ:

            Агентство:  Брокер Плюс
            Агент собственника: {$context->agentName}
            📞 Телефон: {$context->agentPhone}

            #{$context->getHasTag()}
        TEXT;
    }
}
