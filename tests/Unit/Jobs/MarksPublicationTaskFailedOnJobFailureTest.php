<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Jobs\CreateVkLoopStoryJob;
use App\Models\PublicationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MarksPublicationTaskFailedOnJobFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_marks_processing_task_as_failed(): void
    {
        $task = PublicationTask::factory()
            ->ofType(PublicationTaskType::VK_LOOP_STORY)
            ->withStatus(PublicationTaskStatus::PROCESSING)
            ->create();

        $job = new CreateVkLoopStoryJob($task->id);
        $job->failed(new RuntimeException('Worker timeout'));

        $task = $task->fresh();
        $this->assertSame(PublicationTaskStatus::FAILED, $task->status);
        $this->assertSame('Worker timeout', $task->error);
    }

    public function test_failed_uses_default_message_without_exception(): void
    {
        $task = PublicationTask::factory()
            ->ofType(PublicationTaskType::VK_LOOP_STORY)
            ->withStatus(PublicationTaskStatus::PROCESSING)
            ->create();

        $job = new CreateVkLoopStoryJob($task->id);
        $job->failed(null);

        $task = $task->fresh();
        $this->assertSame(PublicationTaskStatus::FAILED, $task->status);
        $this->assertStringContainsString('прервано', (string) $task->error);
    }

    public function test_failed_does_not_overwrite_success_or_failed_status(): void
    {
        $successTask = PublicationTask::factory()
            ->ofType(PublicationTaskType::VK_LOOP_STORY)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();

        $failedTask = PublicationTask::factory()
            ->ofType(PublicationTaskType::VK_LOOP_STORY)
            ->withStatus(PublicationTaskStatus::FAILED)
            ->create(['error' => 'уже была ошибка']);

        (new CreateVkLoopStoryJob($successTask->id))->failed(new RuntimeException('ignored'));
        (new CreateVkLoopStoryJob($failedTask->id))->failed(new RuntimeException('ignored'));

        $this->assertSame(PublicationTaskStatus::SUCCESS, $successTask->fresh()->status);
        $this->assertSame(PublicationTaskStatus::FAILED, $failedTask->fresh()->status);
        $this->assertSame('уже была ошибка', $failedTask->fresh()->error);
    }
}
