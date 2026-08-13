<?php

namespace App\Helpers;

use App\Enums\PublicationTaskType;
use App\Jobs\ArchiveVkProductJob;
use App\Jobs\CreateVkCommentJob;
use App\Jobs\CreateVkLoopStoryJob;
use App\Jobs\CreateVkPostJob;
use App\Jobs\CreateVkProductJob;
use App\Jobs\CreateVkRepostJob;
use App\Jobs\CreateVkStoriesJob;
use App\Jobs\EditVkProductJob;
use App\Jobs\EndVkLoopStoryJob;

final class JobResolver
{
    public function resolve(PublicationTaskType $type): string
    {
        return match ($type) {
            PublicationTaskType::VK_POST => CreateVkPostJob::class,
            PublicationTaskType::VK_STORY => CreateVkStoriesJob::class,
            PublicationTaskType::VK_REPOST => CreateVkRepostJob::class,
            PublicationTaskType::VK_COMMENT => CreateVkCommentJob::class,
            PublicationTaskType::VK_LOOP_STORY => CreateVkLoopStoryJob::class,
            PublicationTaskType::VK_END_LOOP_STORY => EndVkLoopStoryJob::class,
            PublicationTaskType::VK_CREATE_PRODUCT => CreateVkProductJob::class,
            PublicationTaskType::VK_EDIT_PRODUCT => EditVkProductJob::class,
            PublicationTaskType::VK_ARCHIVE_PRODUCT => ArchiveVkProductJob::class,
        };
    }
}
