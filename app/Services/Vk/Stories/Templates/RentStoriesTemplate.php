<?php

declare(strict_types=1);

namespace App\Services\Vk\Stories\Templates;

use App\Interfaces\VkStoriesTemplateInterface;
use App\Services\Vk\Stories\VkStoriesContext;

class RentStoriesTemplate implements VkStoriesTemplateInterface
{
    public function getPrice(VkStoriesContext $context): string
    {
        return $context->getPrice() . ' руб./мес.';
    }

    public function getDetails(VkStoriesContext $context): string
    {
        $rooms = ($roomsValue = $context->getRooms()) !== null ? "Комнат: {$roomsValue}" : '';

        $lines = array_filter([
            "Сдача: {$context->getCategory()}",
            $context->address,
            $rooms,
        ]);

        return implode("\n", $lines);
    }
}
