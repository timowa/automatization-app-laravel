<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Services\Vk\WallPost\VkPostContext;

interface VkPostTemplateInterface
{
    public function generate(VkPostContext $context): string;
}
