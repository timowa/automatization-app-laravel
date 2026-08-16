<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Helpers\JobResolver;
use App\Jobs\CreateVkPostJob;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Models\VkUser;
use App\Scenarios\TaskDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function createPublicationWithTasks(int $count, PublicationTaskStatus $status = PublicationTaskStatus::PENDING): Publication
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();

        foreach (range(1, $count) as $index) {
            PublicationTask::factory()
                ->for($publication)
                ->ofType(PublicationTaskType::VK_POST)
                ->withStatus($status)
                ->create();
        }

        return $publication;
    }

    public function test_dispatch_processes_each_pending_task_once(): void
    {
        $publication = $this->createPublicationWithTasks(3);
        $dispatcher = new TaskDispatcher(new JobResolver);

        $dispatcher->dispatch($publication->id);
        $this->assertDatabaseCount('publication_tasks', 3);
        $this->assertDatabaseHas('publication_tasks', [
            'publication_id' => $publication->id,
            'status' => PublicationTaskStatus::QUEUED->value,
        ]);
        $this->assertDatabaseMissing('publication_tasks', [
            'publication_id' => $publication->id,
            'status' => PublicationTaskStatus::PENDING->value,
        ]);

        Queue::assertPushed(CreateVkPostJob::class, 3);

        $dispatcher->dispatch($publication->id);

        Queue::assertPushed(CreateVkPostJob::class, 3);
        $this->assertDatabaseCount('publication_tasks', 3);
    }

    public function test_dispatch_does_not_pick_waiting_tasks(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();

        $pendingTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::PENDING)
            ->create();

        foreach (range(1, 2) as $index) {
            PublicationTask::factory()
                ->for($publication)
                ->ofType(PublicationTaskType::VK_STORY)
                ->withStatus(PublicationTaskStatus::WAITING)
                ->create();
        }

        $dispatcher = new TaskDispatcher(new JobResolver);
        $dispatcher->dispatch($publication->id);

        $pendingTask = $pendingTask->fresh();
        $this->assertSame(PublicationTaskStatus::QUEUED, $pendingTask->status);
        Queue::assertPushed(CreateVkPostJob::class, 1);
    }

    public function test_dispatch_does_not_pick_queued_tasks(): void
    {
        $publication = $this->createPublicationWithTasks(2, PublicationTaskStatus::QUEUED);
        $dispatcher = new TaskDispatcher(new JobResolver);

        $dispatcher->dispatch($publication->id);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('publication_tasks', 2);
        $this->assertDatabaseMissing('publication_tasks', [
            'publication_id' => $publication->id,
            'status' => PublicationTaskStatus::PENDING->value,
        ]);
    }

    public function test_concurrent_dispatch_no_duplicates(): void
    {
        $publication = $this->createPublicationWithTasks(5);
        $dispatcher = new TaskDispatcher(new JobResolver);

        $dispatcher->dispatch($publication->id);
        $dispatcher->dispatch($publication->id);

        $queuedCount = PublicationTask::where('publication_id', $publication->id)
            ->where('status', PublicationTaskStatus::QUEUED)
            ->count();

        $this->assertSame(5, $queuedCount);
        Queue::assertPushed(CreateVkPostJob::class, 5);
    }
}
