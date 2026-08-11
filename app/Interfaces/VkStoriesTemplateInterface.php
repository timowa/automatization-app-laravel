<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Services\Vk\Stories\VkStoriesContext;

interface VkStoriesTemplateInterface
{
    public function getPrice(VkStoriesContext $context): string;

    public function getDetails(VkStoriesContext $context): string;
}
