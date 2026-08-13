<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\VkPostContext;

class FeedbackTemplate implements VkPostTemplateInterface
{
    public function generate(VkPostContext $context): string
    {
        $title = mb_strtoupper($context->getCategory());

        return <<<TEXT
            ОТЗЫВ о сделке в г. {$context->cityName}!
            
            {$title}: {$context->address}
            
            Спасибо клиентам за доверие!
            
            #broker_plus_post_{$context->offerId}
        TEXT;
    }
}
