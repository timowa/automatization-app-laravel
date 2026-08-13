<?php

namespace App\Console\Commands;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Helpers\JobResolver;
use App\Models\PublicationTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('publication:task-repeat {taskId}')]
#[Description('Command description')]
class PublicationTaskRepeat extends Command
{
    protected $signature = 'publication:task-repeat';
    protected $description = 'Повторение выполнения задания публикации';
    /**
     * Execute the console command.
     */
    public function handle(
        JobResolver $jobResolver,
    )
    {
        $taskId = $this->argument('taskId');
        $task = PublicationTask::findOrFail($taskId);

        if (!$task) {
            $this->error('PublicationTask не найден');
            return;
        }

        $this->info("Повторяем task #{$taskId}");

        $task->update([
            'status' => PublicationTaskStatus::PENDING,
            'error' => null
        ]);

        $job = $jobResolver->resolve($task->type);

        $job::dispatch($task->id);

    }
}
