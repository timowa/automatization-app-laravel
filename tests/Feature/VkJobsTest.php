<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Jobs\ArchiveVkProductJob;
use App\Jobs\CreateVkCommentJob;
use App\Jobs\CreateVkLoopStoryJob;
use App\Jobs\CreateVkPostJob;
use App\Jobs\CreateVkProductJob;
use App\Jobs\CreateVkRepostJob;
use App\Jobs\CreateVkStoriesJob;
use App\Jobs\EditVkProductJob;
use App\Jobs\EndVkLoopStoryJob;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Models\VkGroup;
use App\Models\VkLoopStory;
use App\Models\VkProduct;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Scenarios\ScenarioFactory;
use App\Services\Vk\FakeVkApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VkJobsTest extends TestCase
{
    use RefreshDatabase;

    private FakeVkApiService $vkApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vkApi = new FakeVkApiService;
        Queue::fake();
    }

    private function createPostTask(): PublicationTask
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();

        return PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->create();
    }

    public function test_create_vk_post_job_success(): void
    {
        $task = $this->createPostTask();
        $job = new CreateVkPostJob(
            $task->id,
            new \App\Helpers\ScenarioVkPostTemplateResolver,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $task->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $task->status);
        $this->assertNotNull($task->external_id);
        $this->assertDatabaseHas('vk_posts', ['offer_id' => $task->publication->offer_id]);
    }

    public function test_create_vk_post_job_fails_when_agent_has_no_token(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id, 'vk_token' => '']);
        $publication = Publication::factory()->forOffer($offer)->create();
        $task = PublicationTask::factory()->for($publication)->ofType(PublicationTaskType::VK_POST)->create();

        $job = new CreateVkPostJob(
            $task->id,
            new \App\Helpers\ScenarioVkPostTemplateResolver,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $task->fresh();
        $this->assertSame(PublicationTaskStatus::FAILED, $task->status);
        $this->assertStringContainsString('токен', $task->error ?? '');
    }

    public function test_create_vk_post_job_sets_failed_on_vk_api_exception(): void
    {
        $task = $this->createPostTask();
        $this->vkApi->setFailNext('VKApiException', 'VK blocked');

        $job = new CreateVkPostJob(
            $task->id,
            new \App\Helpers\ScenarioVkPostTemplateResolver,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $task->fresh();
        $this->assertSame(PublicationTaskStatus::FAILED, $task->status);
        $this->assertSame('VK blocked', $task->error);
    }

    public function test_create_vk_repost_job_success(): void
    {
        $group = VkGroup::factory()->create();
        $postTask = $this->createPostTask();
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $repostTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_REPOST)
            ->dependsOn($postTask)
            ->create();

        $job = new CreateVkRepostJob(
            $repostTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $repostTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $repostTask->status);
    }

    public function test_create_vk_stories_job_success(): void
    {
        $postTask = $this->createPostTask();
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $storyTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_STORY)
            ->dependsOn($postTask)
            ->create();

        $this->vkApi->storiesPost = ['count' => 1];

        $job = new CreateVkStoriesJob(
            $storyTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $storyTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $storyTask->status);
    }

    public function test_create_vk_comment_job_success(): void
    {
        $postTask = $this->createPostTask();
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $commentTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_COMMENT)
            ->dependsOn($postTask)
            ->create();

        $job = new CreateVkCommentJob(
            $commentTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $commentTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $commentTask->status);
        $this->assertNotNull($commentTask->external_id);
    }

    public function test_create_vk_loop_story_job_creates_loop_story_record(): void
    {
        $postTask = $this->createPostTask();
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $loopTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_LOOP_STORY)
            ->dependsOn($postTask)
            ->create();

        $job = new CreateVkLoopStoryJob(
            $loopTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $loopTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $loopTask->status);
        $this->assertDatabaseHas('vk_loop_stories', [
            'offer_id' => $postTask->publication->offer_id,
            'is_active' => true,
        ]);
    }

    public function test_end_vk_loop_story_job_deactivates_loop_story(): void
    {
        $offer = Offer::factory()->create();
        $publication = Publication::factory()->forOffer($offer)->create();
        $loopStory = VkLoopStory::factory()->create([
            'offer_id' => $offer->id,
            'task_id' => PublicationTask::factory()->for($publication)->ofType(PublicationTaskType::VK_LOOP_STORY),
            'is_active' => true,
        ]);

        $task = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_END_LOOP_STORY)
            ->create();

        $job = new EndVkLoopStoryJob(
            $task->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle();

        $task->fresh();
        $loopStory->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $task->status);
        $this->assertFalse($loopStory->is_active);
    }

    public function test_create_vk_product_job_success(): void
    {
        VkGroup::factory()->count(2)->create();
        $postTask = $this->createPostTask();
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $productTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->dependsOn($postTask)
            ->create();

        $job = new CreateVkProductJob(
            $productTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $productTask->status);
        $this->assertDatabaseCount('vk_products', 2);
    }

    public function test_edit_vk_product_job_success(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $parentTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();
        VkProduct::factory()->count(2)->create([
            'offer_id' => $offer->id,
            'agent_id' => $offer->agent_id,
            'task_id' => $parentTask->id,
            'is_archived' => false,
        ]);

        $editTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_EDIT_PRODUCT)
            ->dependsOn($parentTask)
            ->create();

        $job = new EditVkProductJob(
            $editTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $editTask = $editTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $editTask->status);
    }

    public function test_archive_vk_product_job_success(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $parentTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();
        VkProduct::factory()->count(2)->create([
            'offer_id' => $offer->id,
            'agent_id' => $offer->agent_id,
            'group_id' => 100,
            'task_id' => $parentTask->id,
            'is_archived' => false,
        ]);

        $archiveTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_ARCHIVE_PRODUCT)
            ->dependsOn($parentTask)
            ->create();

        $job = new ArchiveVkProductJob(
            $archiveTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $archiveTask = $archiveTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $archiveTask->status);
        $this->assertDatabaseMissing('vk_products', [
            'offer_id' => $offer->id,
            'is_archived' => false,
        ]);
    }

    public function test_archive_vk_product_job_no_op_when_no_products(): void
    {
        $offer = Offer::factory()->create();
        $publication = Publication::factory()->forOffer($offer)->create();
        $archiveTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_ARCHIVE_PRODUCT)
            ->create();

        $job = new ArchiveVkProductJob(
            $archiveTask->id,
            new \App\Helpers\PublicationTaskDependencyResolver,
            new \App\Scenarios\TaskDispatcher(new \App\Helpers\JobResolver)
        );

        $job->handle($this->vkApi);

        $archiveTask = $archiveTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $archiveTask->status);
    }
}
