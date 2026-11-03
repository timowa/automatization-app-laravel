<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PublicationTaskStatus as Status;
use App\Models\Publication;
use App\Scenarios\TaskDispatcher;
use Illuminate\Support\Facades\DB;

final class RetryPublicationTasksAction
{
    public function execute(Publication $publication): int
    {
        $count = DB::transaction(function () use ($publication): int {
            Publication::whereKey($publication->id)->lockForUpdate()->firstOrFail();
            $tasks = $publication->tasks()->orderBy('id')->get()->keyBy('id');
            $failed = $tasks->filter(fn ($task) => $task->status === Status::FAILED);
            foreach ($failed as $task) {
                $parent = $tasks->get($task->dependent_task_id);
                $task->update([
                    'status' => $parent !== null && $parent->status !== Status::SUCCESS ? Status::WAITING : Status::PENDING,
                    'error' => null,
                ]);
            }
            if ($failed->isNotEmpty() && $publication->exclusive_run_id !== null) {
                $publication->exclusiveRun->update(['status' => 'processing', 'completed_at' => null]);
            }

            return $failed->count();
        });
        app(TaskDispatcher::class)->dispatch($publication->id);

        return $count;
    }
}
