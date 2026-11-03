<?php

namespace App\Scenarios;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Helpers\JobResolver;
use App\Models\Publication;
use Illuminate\Support\Facades\DB;

class TaskDispatcher
{
    public function __construct(private JobResolver $jobResolver) {}

    public function dispatch(int $publicationId): void
    {
        $tasks = DB::transaction(function () use ($publicationId) {
            $tasks = DB::table('publication_tasks')->where('publication_id', $publicationId)
                ->where('status', PublicationTaskStatus::PENDING->value)->lockForUpdate()->get();
            DB::table('publication_tasks')->whereIn('id', $tasks->pluck('id'))
                ->update(['status' => PublicationTaskStatus::QUEUED->value, 'updated_at' => now()]);

            return $tasks;
        });
        foreach ($tasks as $task) {
            $type = PublicationTaskType::from($task->type);
            $job = $this->jobResolver->resolve($type);
            try {
                if ($type === PublicationTaskType::VK_POST) {
                    $job::dispatch($task->id, 0, Publication::findOrFail($publicationId)->agent_id);
                } else {
                    $job::dispatch($task->id);
                }
            } catch (\Throwable $e) {
                DB::table('publication_tasks')->where('id', $task->id)->where('status', 'queued')->update(['status' => 'pending']);
                throw $e;
            }
        }
    }
}
