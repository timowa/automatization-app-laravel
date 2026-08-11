<?php

namespace App\Scenarios;

use App\Enums\PublicationTaskStatus;
use App\Helpers\JobResolver;
use App\Models\PublicationTask;
use Illuminate\Support\Facades\DB;

class TaskDispatcher
{
    public function __construct(private JobResolver $jobResolver)
    {

    }
    public function dispatch(int $publicationId)
    {
        $tasks = DB::table('publication_tasks')
            ->where('publication_id', $publicationId)
            ->where('status', PublicationTaskStatus::PENDING)
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        foreach ($tasks as $task) {
            $job = $this->jobResolver->resolve($task->type);
            $job::dispatch($task->id);
        }
    }
}
