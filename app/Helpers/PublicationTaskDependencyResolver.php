<?php

namespace App\Helpers;

use App\Enums\PublicationTaskStatus;
use App\Models\PublicationTask;

final class PublicationTaskDependencyResolver
{
    public function release(int $completedTaskId): void
    {
        PublicationTask::query()
            ->where('dependent_task_id', $completedTaskId)
            ->where('status', PublicationTaskStatus::WAITING)
            ->update([
                'status' => PublicationTaskStatus::PENDING,
            ]);
    }
}
