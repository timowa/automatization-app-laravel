<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\VkPostContext;

class RentTemplate implements VkPostTemplateInterface
{
    public function generate(VkPostContext $context): string
    {
        $commissionLine = $context->getCommission() !== null
            ? "Комиссия агентства: {$context->getCommission()} руб."
            : 'Комиссия агентства: уточняйте';

        $depositLine = $context->getDeposit() !== null
            ? "Залог: {$context->getDeposit()} руб."
            : 'Залог: уточняйте';

        $rooms = ($roomsValue = $context->getRooms()) !== null ? "Комнаты: {$roomsValue}" : '';
        $area = ($areaValue = $context->getArea()) !== null ? "Общая площадь: {$areaValue}" : '';
        $kitchenArea = ($kitchenValue = $context->getKitchenArea()) !== null ? "Площадь кухни: {$kitchenValue}" : '';
        $floorLine = ($floorValue = $context->getFloorLine()) !== null ? $floorValue : '';

        $details = array_filter([$rooms, $area, $kitchenArea, $floorLine]);
        $detailsBlock = $details !== [] ? "\n" . implode("\n", $details) : '';

        $title = mb_strtoupper($context->getCategory());

        return <<<TEXT
            СДАЕТСЯ {$title} в г. {$context->cityName}!

            Адрес: {$context->address}{$detailsBlock}

            Аренда: {$context->getPrice()} руб./мес.
            {$commissionLine}
            {$depositLine}

            КОНТАКТЫ:

            Агентство: Брокер Плюс
            Агент: {$context->agentName}
            📞 Телефон: {$context->agentPhone}

            #{$context->getHasTag()}
        TEXT;
    }
}
