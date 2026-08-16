<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Helpers\JobResolver;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Helpers\ScenarioVkPostTemplateResolver;
use App\Jobs\CreateVkPostJob;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Models\VkUser;
use App\Scenarios\TaskDispatcher;
use App\Services\Vk\FakeVkApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskRepeatCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeVkApiService $vkApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vkApi = new FakeVkApiService;
        Queue::fake();
    }

    public function test_task_repeat_sets_queued_and_dispatches(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $task = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::FAILED)
            ->create([
                'error' => 'previous error',
            ]);

        $this->artisan('publication:task-repeat', ['taskId' => $task->id])
            ->assertSuccessful()
            ->expectsOutput("Повторяем task #{$task->id}")
            ->expectsOutput("Task #{$task->id} поставлен в очередь");

        $task = $task->fresh();
        $this->assertSame(PublicationTaskStatus::QUEUED, $task->status);
        $this->assertNull($task->error);
        Queue::assertPushed(CreateVkPostJob::class, 1);
    }

    public function test_task_repeat_skips_success_tasks(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $task = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();

        $this->artisan('publication:task-repeat', ['taskId' => $task->id])
            ->expectsOutput('Задача уже выполнена');

        $task = $task->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $task->status);
        Queue::assertNothingPushed();
    }

    public function test_job_guard_skips_non_queued_task(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $task = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();

        $job = new CreateVkPostJob($task->id);
        $job->handle(
            $this->vkApi,
            new ScenarioVkPostTemplateResolver,
            new PublicationTaskDependencyResolver,
            new TaskDispatcher(new JobResolver),
        );

        $task = $task->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $task->status);
    }
}
