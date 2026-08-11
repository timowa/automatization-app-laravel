<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost;

use App\Interfaces\VkPostTemplateInterface;

class VkPostGenerator
{
    public function generate(VkPostContext $context, VkPostTemplateInterface $template): string
    {
        return $template->generate($context);
    }
}
