<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Enums\PublicationTaskStatus;
use App\Models\PublicationTask;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Переводит PublicationTask из PROCESSING в FAILED, если queue job упал
 * (таймаут воркера, SIGKILL, необработанное исключение) и handle не успел
 * обновить статус сам.
 *
 * Ожидает свойство int $taskId у job.
 */
trait MarksPublicationTaskFailedOnJobFailure
{
    public function failed(?Throwable $e): void
    {
        $task = PublicationTask::query()->find($this->taskId);
        if ($task === null) {
            return;
        }

        if ($task->status !== PublicationTaskStatus::PROCESSING) {
            return;
        }

        $message = $e !== null && $e->getMessage() !== ''
            ? $e->getMessage()
            : 'Выполнение задачи прервано (таймаут или остановка воркера)';

        $task->update([
            'status' => PublicationTaskStatus::FAILED,
            'error' => $message,
        ]);

        Log::channel('job')->warning('Задача прервана и переведена в ошибку', [
            'task_id' => $this->taskId,
        ]);

        if ($e !== null) {
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
