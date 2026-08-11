<?php

namespace App\Helpers;

use App\Enums\PublicationTaskType;

final class PublicationTaskDependenceInspector
{
    public function inspect(PublicationTaskType $type): ?PublicationTaskType
    {
        return match ($type) {
            PublicationTaskType::VK_STORY,
            PublicationTaskType::VK_COMMENT,
            PublicationTaskType::VK_REPOST,
            PublicationTaskType::VK_PRODUCT => PublicationTaskType::VK_POST,
            default => null
        };
    }
}
