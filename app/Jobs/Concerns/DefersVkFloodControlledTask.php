<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Enums\PublicationTaskStatus;
use App\Helpers\VkApiErrorInspector;
use App\Models\PublicationTask;
use Illuminate\Support\Facades\Log;
use Throwable;

trait DefersVkFloodControlledTask
{
    protected function shouldHandleTask(PublicationTask $task): bool
    {
        if ($task->status === PublicationTaskStatus::QUEUED) {
            return true;
        }

        return $this->floodAttempt > 0
            && $task->status === PublicationTaskStatus::PROCESSING;
    }

    protected function deferOnFlood(PublicationTask $task, Throwable $e): bool
    {
        if (! VkApiErrorInspector::isFlood($e)) {
            return false;
        }

        if ($this->floodAttempt + 1 >= VkApiErrorInspector::FLOOD_MAX_ATTEMPTS) {
            return false;
        }

        Log::channel('job')->warning('Задача отложена: ВКонтакте просит подождать', [
            'task_id' => $task->id,
            'attempt' => $this->floodAttempt + 1,
        ]);

        self::dispatch($this->taskId, $this->floodAttempt + 1)
            ->delay(now()->addSeconds(VkApiErrorInspector::FLOOD_RETRY_DELAY_SECONDS));

        return true;
    }
}
