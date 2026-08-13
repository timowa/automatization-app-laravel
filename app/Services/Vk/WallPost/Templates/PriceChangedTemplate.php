<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\VkPostContext;

class PriceChangedTemplate implements VkPostTemplateInterface
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
        $oldPrice = $context->getOldPrice();
        $newPrice = $context->getPrice();
        $diff = $context->oldPrice !== null ? formatPrice($context->oldPrice - $context->price) : '0';

        return <<<TEXT
            ЦЕНА СНИЖЕНА! {$title} в г. {$context->cityName}!
             
            Адрес: {$context->address}{$detailsBlock}
            
            Старая цена: {$oldPrice} руб.
            Новая цена: {$newPrice} руб.
            Выгода: {$diff} руб.
            
            КОНТАКТЫ:
            
            Агентство:  Брокер Плюс
            Агент собственника: {$context->agentName}
            📞 Телефон: {$context->agentPhone}
            
            #broker_plus_post_{$context->offerId}
        TEXT;
    }
}
