<?php

namespace App\Console\Commands;

use App\Actions\RetryPublicationTasksAction;
use App\Enums\PublicationTaskStatus;
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
    public function handle(RetryPublicationTasksAction $action): void
    {
        $taskId = $this->argument('taskId');
        $task = PublicationTask::findOrFail($taskId);

        if ($task->status === PublicationTaskStatus::SUCCESS) {
            $this->info('Задача уже выполнена');

            return;
        }
        if ($task->status !== PublicationTaskStatus::FAILED) {
            $this->info('Задача ещё выполняется или ожидает выполнения');

            return;
        }

        $this->info("Повторяем task #{$taskId}");

        $action->execute($task->publication);

        $this->info("Task #{$taskId} поставлен в очередь");
    }
}
