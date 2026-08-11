<?php

namespace App\Helpers;

use App\Enums\PublicationTaskType;
use App\Jobs\CreateVkPostJob;
use App\Jobs\CreateVkRepostJob;
use App\Jobs\CreateVkStoriesJob;

final class JobResolver
{
    public function resolve(PublicationTaskType $type)
    {
        return match ($type) {
            PublicationTaskType::VK_POST => CreateVKPostJob::class,
            PublicationTaskType::VK_STORY => CreateVkStoriesJob::class,
            PublicationTaskType::VK_REPOST => CreateVkRepostJob::class,
        };
    }
}
