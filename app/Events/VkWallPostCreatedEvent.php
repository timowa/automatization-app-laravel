<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\VkWallPost;

class VkWallPostCreatedEvent
{
    public function __construct(public VkWallPost $vkWallPost)
    {
    }
}
