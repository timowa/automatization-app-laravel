<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\City;
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
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Models\VkGroup;
use App\Models\VkLoopStory;
use App\Models\VkProduct;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Helpers\JobResolver;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Helpers\ScenarioVkPostTemplateResolver;
use App\Scenarios\TaskDispatcher;
use App\Services\Vk\Comment\CommentTextProvider;
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


    private function jobDependencies(): array
    {
        return [
            new PublicationTaskDependencyResolver,
            new TaskDispatcher(new JobResolver),
        ];
    }

    private function commentJobDependencies(): array
    {
        return array_merge($this->jobDependencies(), [new CommentTextProvider]);
    }

    private function postJobDependencies(): array
    {
        return [
            new ScenarioVkPostTemplateResolver,
            new PublicationTaskDependencyResolver,
            new TaskDispatcher(new JobResolver),
        ];
    }

    private function createPostTask(): PublicationTask
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();

        return PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();
    }

    private function createPostTaskForCity(int $city): PublicationTask
    {
        $offer = Offer::factory()->create(['city' => $city]);
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();

        return PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();
    }

    public function test_city_accepted_cities_mapping(): void
    {
        $this->assertSame(
            [City::ABAKAN, City::CHIKAGO],
            City::ABAKAN->acceptedCities()
        );
        $this->assertSame(
            [City::KYZYL],
            City::KYZYL->acceptedCities()
        );
        $this->assertSame(
            [City::ABAKAN, City::CHIKAGO],
            City::CHIKAGO->acceptedCities()
        );
    }

    public function test_create_vk_post_job_success(): void
    {
        $task = $this->createPostTask();
        $job = new CreateVkPostJob($task->id);

        $job->handle($this->vkApi, ...$this->postJobDependencies());

        $task = $task->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $task->status);
        $this->assertNotNull($task->external_id);
        $this->assertDatabaseHas('vk_posts', ['offer_id' => $task->publication->offer_id]);
    }

    public function test_create_vk_post_job_fails_when_agent_has_no_token(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id, 'vk_token' => '']);
        $publication = Publication::factory()->forOffer($offer)->create();
        $task = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkPostJob($task->id);

        $job->handle($this->vkApi, ...$this->postJobDependencies());

        $task = $task->fresh();
        $this->assertSame(PublicationTaskStatus::FAILED, $task->status);
        $this->assertStringContainsString('токен', $task->error ?? '');
    }

    public function test_create_vk_post_job_sets_failed_on_vk_api_exception(): void
    {
        $task = $this->createPostTask();
        $this->vkApi->setFailNext('VKApiException', 'VK blocked');

        $job = new CreateVkPostJob($task->id);

        $job->handle($this->vkApi, ...$this->postJobDependencies());

        $task = $task->fresh();
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
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkRepostJob($repostTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $repostTask = $repostTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $repostTask->status);
        $repostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createReposts');
        $this->assertCount(1, $repostCalls);
        $this->assertSame([$group->group_id], $repostCalls[array_key_first($repostCalls)]['args'] ?? null);
    }

    public function test_create_vk_repost_job_filters_by_city_mapping(): void
    {
        $abakanGroup = VkGroup::factory()->create(['city' => 1]);
        $kyzylGroup = VkGroup::factory()->create(['city' => 2]);
        $postTask = $this->createPostTaskForCity(2);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $repostTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_REPOST)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkRepostJob($repostTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $repostTask = $repostTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $repostTask->status);

        $repostCalls = array_values(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createReposts'));
        $this->assertCount(1, $repostCalls);
        $this->assertSame([$kyzylGroup->group_id], $repostCalls[0]['args'] ?? null);
    }

    public function test_create_vk_repost_job_abakan_group_accepts_chikago_offer(): void
    {
        $abakanGroup = VkGroup::factory()->create(['city' => 1]);
        $postTask = $this->createPostTaskForCity(3);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $repostTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_REPOST)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkRepostJob($repostTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $repostTask = $repostTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $repostTask->status);

        $repostCalls = array_values(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createReposts'));
        $this->assertCount(1, $repostCalls);
        $this->assertSame([$abakanGroup->group_id], $repostCalls[0]['args'] ?? null);
    }

    public function test_create_vk_repost_job_kyzyl_group_rejects_abakan_offer(): void
    {
        VkGroup::factory()->create(['city' => 2]);
        $postTask = $this->createPostTaskForCity(1);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $repostTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_REPOST)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkRepostJob($repostTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $repostTask = $repostTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $repostTask->status);
        $this->assertEmpty(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createReposts'));
    }

    public function test_create_vk_repost_job_success_when_no_groups_for_city(): void
    {
        VkGroup::factory()->create(['city' => 2]);
        $postTask = $this->createPostTaskForCity(1);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $repostTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_REPOST)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkRepostJob($repostTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $repostTask = $repostTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $repostTask->status);
        $this->assertEmpty(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createReposts'));
    }

    public function test_create_vk_stories_job_success(): void
    {
        $postTask = $this->createPostTask();
        $postTask->publication->offer->update(['images' => [base_path('storage/app/assets/images/vkstory.png')]]);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $storyTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_STORY)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $this->vkApi->storiesPostResponse = ['count' => 1];

        $job = new CreateVkStoriesJob($storyTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $storyTask = $storyTask->fresh();
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
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkCommentJob($commentTask->id);

        $job->handle($this->vkApi, ...$this->commentJobDependencies());

        $commentTask = $commentTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $commentTask->status);
        $this->assertNotNull($commentTask->external_id);
    }

    public function test_create_vk_loop_story_job_creates_loop_story_record(): void
    {
        $postTask = $this->createPostTask();
        $postTask->publication->offer->update(['images' => [base_path('storage/app/assets/images/vkstory.png')]]);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $loopTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_LOOP_STORY)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $this->vkApi->storiesPostResponse = ['count' => 1];

        $job = new CreateVkLoopStoryJob($loopTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $loopTask = $loopTask->fresh();
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

        $postTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();
        $post = VkWallPost::factory()->forOffer($offer->id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id]);

        $task = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_END_LOOP_STORY)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new EndVkLoopStoryJob($task->id);

        $job->handle(...$this->jobDependencies());

        $task = $task->fresh();
        $loopStory = $loopStory->fresh();
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
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkProductJob($productTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $productTask = $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $productTask->status);
        $this->assertDatabaseCount('vk_products', 2);

        $uploadCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'uploadMarketPhoto');
        $batchCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createProductsBatch');

        $this->assertCount(2, $uploadCalls);
        $this->assertCount(2, $batchCalls);
    }

    public function test_edit_vk_product_job_success(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $post = VkWallPost::factory()->forOffer($offer->id)->create();
        $parentTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();
        $parentTask->update(['external_id' => (string) $post->id]);
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
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new EditVkProductJob($editTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $editTask = $editTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $editTask->status);

        $batchCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'editProductsBatch');
        $this->assertCount(1, $batchCalls);
    }

    public function test_archive_vk_product_job_success(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $post = VkWallPost::factory()->forOffer($offer->id)->create();
        $parentTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();
        $parentTask->update(['external_id' => (string) $post->id]);
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
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new ArchiveVkProductJob($archiveTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $archiveTask = $archiveTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $archiveTask->status);
        $this->assertDatabaseMissing('vk_products', [
            'offer_id' => $offer->id,
            'is_archived' => false,
        ]);

        $batchCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'archiveProductsBatch');
        $this->assertCount(1, $batchCalls);
    }

    public function test_archive_vk_product_job_no_op_when_no_products(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $publication = Publication::factory()->forOffer($offer)->create();
        $post = VkWallPost::factory()->forOffer($offer->id)->create();
        $parentTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();
        $parentTask->update(['external_id' => (string) $post->id]);
        $archiveTask = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_ARCHIVE_PRODUCT)
            ->dependsOn($parentTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new ArchiveVkProductJob($archiveTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $archiveTask = $archiveTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $archiveTask->status);
    }

    public function test_create_vk_product_job_chunks_large_group_lists(): void
    {
        VkGroup::factory()->count(30)->create();
        $postTask = $this->createPostTask();
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $productTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkProductJob($productTask->id);

        $job->handle($this->vkApi, ...$this->jobDependencies());

        $productTask = $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $productTask->status);
        $this->assertDatabaseCount('vk_products', 30);

        $batchCalls = array_values(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createProductsBatch'));
        $this->assertCount(30, $batchCalls);
    }

    public function test_create_vk_product_job_filters_by_city_mapping(): void
    {
        $abakanGroup = VkGroup::factory()->create(['city' => 1]);
        $kyzylGroup = VkGroup::factory()->create(['city' => 2]);
        $postTask = $this->createPostTaskForCity(2);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $productTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkProductJob($productTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $productTask = $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $productTask->status);
        $this->assertDatabaseCount('vk_products', 1);
        $this->assertDatabaseHas('vk_products', [
            'offer_id' => $postTask->publication->offer_id,
            'group_id' => $kyzylGroup->group_id,
        ]);

        $batchCalls = array_values(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createProductsBatch'));
        $this->assertCount(1, $batchCalls);
    }

    public function test_create_vk_product_job_abakan_group_accepts_chikago_offer(): void
    {
        $abakanGroup = VkGroup::factory()->create(['city' => 1]);
        $postTask = $this->createPostTaskForCity(3);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $productTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkProductJob($productTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $productTask = $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $productTask->status);
        $this->assertDatabaseCount('vk_products', 1);
        $this->assertDatabaseHas('vk_products', [
            'offer_id' => $postTask->publication->offer_id,
            'group_id' => $abakanGroup->group_id,
        ]);
    }

    public function test_create_vk_product_job_kyzyl_group_rejects_abakan_offer(): void
    {
        VkGroup::factory()->create(['city' => 2]);
        $postTask = $this->createPostTaskForCity(1);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $productTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkProductJob($productTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $productTask = $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $productTask->status);
        $this->assertDatabaseCount('vk_products', 0);
        $this->assertEmpty(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createProductsBatch'));
    }

    public function test_create_vk_product_job_success_when_no_groups_for_city(): void
    {
        VkGroup::factory()->create(['city' => 2]);
        $postTask = $this->createPostTaskForCity(1);
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $productTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $job = new CreateVkProductJob($productTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $productTask = $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::SUCCESS, $productTask->status);
        $this->assertEmpty(array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'createProductsBatch'));
    }

    public function test_create_vk_product_job_fails_when_zero_products_created(): void
    {
        VkGroup::factory()->count(2)->create();
        $postTask = $this->createPostTask();
        $post = VkWallPost::factory()->forOffer($postTask->publication->offer_id)->forTask($postTask->id)->create();
        $postTask->update(['external_id' => (string) $post->id, 'status' => PublicationTaskStatus::SUCCESS]);

        $productTask = PublicationTask::factory()
            ->for($postTask->publication)
            ->ofType(PublicationTaskType::VK_CREATE_PRODUCT)
            ->dependsOn($postTask)
            ->withStatus(PublicationTaskStatus::QUEUED)
            ->create();

        $this->vkApi->setFailNext('RuntimeException', 'Batch failed');

        $job = new CreateVkProductJob($productTask->id);
        $job->handle($this->vkApi, ...$this->jobDependencies());

        $productTask = $productTask->fresh();
        $this->assertSame(PublicationTaskStatus::FAILED, $productTask->status);
        $this->assertDatabaseCount('vk_products', 0);
    }
}
