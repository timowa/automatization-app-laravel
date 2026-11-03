<?php

use App\Actions\GetAgentPublicationStatsAction;
use App\Actions\ReceiveOfferWebhookAction;
use App\Actions\RetryPublicationTasksAction;
use App\Enums\PublicationTaskStatus as Status;
use App\Enums\PublicationTaskType as Type;
use App\Enums\ScenarioType;
use App\Events\OfferCreatedEvent;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Helpers\ScenarioVkPostTemplateResolver;
use App\Jobs\CreateVkPostJob;
use App\Jobs\CreateVkStoriesJob;
use App\Listeners\ProcessOfferListener;
use App\Models\Agent;
use App\Models\ExclusivePublicationRun;
use App\Models\Offer;
use App\Models\OfferImage;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Models\VkPostStat;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Scenarios\TaskDispatcher;
use App\Services\Exclusive\ExclusivePublicationService;
use App\Services\PublicationCompletionService;
use App\Services\PublicationImages;
use App\Services\Vk\Stories\VkStoriesContextFactory;
use App\Services\Vk\VkApiService;
use App\Services\Vk\WallPost\VkPostContextFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function exclusiveFixture(array $attributes = []): array
{
    $offer = Offer::factory()->create(['exclusive' => true, ...$attributes]);
    $source = Publication::factory()->forOffer($offer)->create(['agent_id' => $offer->agent_id]);
    $recipient = Agent::factory()->create();
    Offer::factory()->create(['city' => $offer->city, 'agent_id' => $recipient->id, 'skip_reason' => 'Не выбран сценарий']);

    return [$offer, $source, $recipient];
}

function exclusiveTask(Publication $publication, Status $status, ?PublicationTask $parent = null, Type $type = Type::VK_POST): PublicationTask
{
    return PublicationTask::factory()->create([
        'publication_id' => $publication->id, 'type' => $type,
        'status' => $status, 'dependent_task_id' => $parent?->id,
    ]);
}

it('waits for terminal tasks, cascades blocked failures and starts once', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    $post = exclusiveTask($source, Status::PROCESSING);
    $story = exclusiveTask($source, Status::WAITING, $post, Type::VK_STORY);
    exclusiveTask($source, Status::SUCCESS, null, Type::VK_UPLOAD_IMAGES);
    $service = app(PublicationCompletionService::class);
    $service->check($source->id);
    expect(ExclusivePublicationRun::count())->toBe(0);
    $post->update(['status' => Status::FAILED]);
    $service->check($source->id);
    $service->check($source->id);
    expect($story->fresh()->status)->toBe(Status::FAILED)
        ->and(ExclusivePublicationRun::count())->toBe(1)
        ->and(Publication::whereNotNull('exclusive_run_id')->count())->toBe(1);
    $child = Publication::whereNotNull('exclusive_run_id')->first();
    expect($child->agent_id)->toBe($recipient->id)
        ->and($child->tasks->pluck('type')->all())->toBe([Type::VK_UPLOAD_IMAGES, Type::VK_POST, Type::VK_STORY])
        ->and($offer->fresh()->publication->id)->toBe($source->id);
});

it('selects recipients from offers only, without duplicates or the owner', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    Offer::factory()->create(['city' => $offer->city, 'agent_id' => $recipient->id]);
    Offer::factory()->create(['city' => $offer->city + 1]);
    $run = app(ExclusivePublicationService::class)->start($offer, $source, 'completion');
    expect($run->recipient_ids)->toBe([$recipient->id]);
});

it('skips scenarios without templates', function (ScenarioType $scenario) {
    [$offer, $source] = exclusiveFixture();
    $source->update(['scenario' => $scenario]);
    app(PublicationCompletionService::class)->check($source->id);
    expect(ExclusivePublicationRun::count())->toBe(0)
        ->and(Publication::count())->toBe(1)
        ->and($offer->fresh()->exclusive_skip_reason)->toContain('нет шаблона');
})->with([ScenarioType::WITHDRAWN, ScenarioType::DELAYED, ScenarioType::DELETED]);

it('uses the last main scenario for a transition and does not create a main publication', function () {
    [$previous, $source] = exclusiveFixture(['exclusive' => false]);
    $current = Offer::factory()->create(['code' => $previous->code, 'agent_id' => $previous->agent_id, 'city' => $previous->city, 'exclusive' => true, 'exclusive_transition' => true, 'price' => 900000]);
    app(ProcessOfferListener::class)->handle(new OfferCreatedEvent($previous->id, $current->id));
    expect($current->fresh()->publication)->toBeNull()
        ->and(ExclusivePublicationRun::first()->scenario)->toBe(ScenarioType::SALE)
        ->and(ExclusivePublicationRun::first()->offer_id)->toBe($current->id);
});

it('does not look past a last main scenario without a template', function () {
    [$previous] = exclusiveFixture(['exclusive' => false]);
    Publication::factory()->forOffer($previous)->create(['scenario' => ScenarioType::WITHDRAWN]);
    $current = Offer::factory()->create(['code' => $previous->code, 'exclusive' => true, 'exclusive_transition' => true]);
    app(ProcessOfferListener::class)->handle(new OfferCreatedEvent($previous->id, $current->id));
    expect(ExclusivePublicationRun::count())->toBe(0)->and($current->fresh()->exclusive_skip_reason)->toContain('нет шаблона');
});

it('skips a transition without a historical main publication', function () {
    $previous = Offer::factory()->create();
    $current = Offer::factory()->create(['code' => $previous->code, 'exclusive' => true, 'exclusive_transition' => true]);
    app(ProcessOfferListener::class)->handle(new OfferCreatedEvent($previous->id, $current->id));
    expect(ExclusivePublicationRun::count())->toBe(0)->and($current->fresh()->exclusive_skip_reason)->toBe('Нет основной публикации');
});

it('enforces a rolling day for transitions across snapshots and allows the exact boundary', function () {
    $this->freezeTime();
    [$offer, $source] = exclusiveFixture();
    $service = app(ExclusivePublicationService::class);
    $service->start($offer, $source, 'completion');
    $next = Offer::factory()->create(['code' => $offer->code, 'exclusive' => true]);
    $this->travel(23)->hours();
    expect($service->start($next, $source, 'transition'))->toBeNull();
    $this->travel(1)->hours();
    expect($service->start($next, $source, 'transition'))->not->toBeNull();
    expect(ExclusivePublicationRun::count())->toBe(2);
});

it('does not apply the cooldown to normal scenario completion', function () {
    [$offer, $source] = exclusiveFixture();
    $service = app(ExclusivePublicationService::class);
    $service->start($offer, $source, 'completion');
    $next = Offer::factory()->create(['code' => $offer->code, 'exclusive' => true]);
    $nextSource = Publication::factory()->forOffer($next)->create();
    expect($service->start($next, $nextSource, 'completion'))->not->toBeNull();
});

it('keeps recipients fixed and resumes the same run', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    Offer::factory()->create(['city' => $offer->city]);
    $service->expand($run);
    $service->expand($run);
    expect($run->publications()->count())->toBe(1)
        ->and($run->publications()->first()->agent_id)->toBe($recipient->id);
});

it('does not recurse and retries failed dependent tasks in order', function () {
    [$offer, $source] = exclusiveFixture();
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $publication = $run->publications()->first();
    $publication->tasks()->update(['status' => 'failed']);
    app(PublicationCompletionService::class)->check($publication->id);
    expect($run->fresh()->status)->toBe('failed')->and(ExclusivePublicationRun::count())->toBe(1);
    app(RetryPublicationTasksAction::class)->execute($publication);
    $tasks = $publication->tasks()->orderBy('id')->get();
    expect($tasks[0]->status)->toBe(Status::QUEUED)
        ->and($tasks[1]->status)->toBe(Status::WAITING)
        ->and($tasks[2]->status)->toBe(Status::WAITING)
        ->and($run->fresh()->status)->toBe('processing');
});

it('uses recipient contacts and keeps photo uploads separate', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    VkUser::factory()->create(['agent_id' => $recipient->id, 'vk_user_id' => 98765]);
    $image = OfferImage::factory()->create(['offer_id' => $offer->id, 'media_id' => 123]);
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $publication = $run->publications()->first();
    $images = app(PublicationImages::class);
    $upload = $images->images($publication)->first();
    expect($upload->media_id)->toBeNull();
    $upload->update(['media_id' => 456]);
    expect($images->attachments($publication))->toBe(['photo98765_456'])
        ->and($image->fresh()->media_id)->toBe(123);
    $context = app(VkPostContextFactory::class)->getContext($offer->id, $recipient->id);
    expect($context->agentName)->toBe($recipient->name)->and($context->agentPhone)->toBe($recipient->phone);
});

it('posts using the source template and the recipient account', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    VkUser::factory()->create(['agent_id' => $recipient->id, 'vk_user_id' => 98765]);
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $publication = $run->publications()->first();
    $postTask = $publication->tasks()->where('type', Type::VK_POST)->first();
    $postTask->update(['status' => Status::QUEUED]);
    $api = Mockery::mock(VkApiService::class);
    $api->shouldReceive('setToken')->once();
    $api->shouldReceive('wallPost')->once()->withArgs(fn ($owner, $text, $attachments, $guid) => $owner === 98765 && str_contains($text, $recipient->name) && $guid === 'publication-task-'.$postTask->id)->andReturn(['post_id' => 42]);
    (new CreateVkPostJob($postTask->id, 0, $recipient->id))->handle($api, app(ScenarioVkPostTemplateResolver::class), app(PublicationTaskDependencyResolver::class), app(TaskDispatcher::class));
    expect($postTask->fresh()->status)->toBe(Status::SUCCESS);
    $this->assertDatabaseHas('vk_posts', ['offer_id' => $offer->id, 'owner_id' => 98765, 'post_id' => 42]);
});

it('shows exclusive detail and keeps one list row per offer', function () {
    [$offer, $source] = exclusiveFixture();
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $this->withSession(['is_admin' => true])->get('/offers?status=&code='.$offer->code.'&exclusive=1')
        ->assertOk()->assertViewHas('offers', fn ($offers) => $offers->total() === 1);
    $this->get('/offers/'.$offer->id.'/exclusive')->assertOk()->assertSee('Повторить ошибки эксклюзивных публикаций');
});

it('recovers a persisted run that was not expanded', function () {
    [$offer, $source] = exclusiveFixture();
    $run = app(ExclusivePublicationService::class)->start($offer, $source, 'completion');
    $this->artisan('publications:recover-exclusive')->assertSuccessful();
    expect($run->publications()->count())->toBe(1);
});

function exclusivePayload(Agent $agent, array $overrides = []): array
{
    return ['offers' => [[
        'id' => 101, 'code' => 'EX-101', 'url' => 'https://example.com/101',
        'agent' => ['phone' => $agent->phone],
        'location' => ['city' => 'Кызыл', 'address' => 'ул. Калинина, 24'],
        'deal' => 'продажа', 'category' => 'квартира', 'price' => 1500000,
        'status' => 'актив', 'stage' => 3, ...$overrides,
    ]]];
}

it('validates strict boolean values in the webhook', function ($value) {
    $agent = Agent::factory()->create();
    $this->withHeader('Authorization', 'Bearer '.config('app.api_key'))
        ->postJson('/offer', exclusivePayload($agent, ['exclusive' => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors('offers.0.exclusive');
})->with(['string true' => ['true'], 'string false' => ['false'], 'one' => [1], 'zero' => [0], 'null' => [null]]);

it('persists defaults, detects transitions and ignores saved duplicate and rejected snapshots', function () {
    $agent = Agent::factory()->create();
    $action = app(ReceiveOfferWebhookAction::class);
    $action->execute(exclusivePayload($agent));
    $first = Offer::latest('id')->first();
    expect($first->exclusive)->toBeFalse();
    $action->execute(exclusivePayload($agent));
    Offer::factory()->create(['code' => 'EX-101', 'exclusive' => true, 'payload' => ['invalid' => true], 'skip_reason' => 'Ошибка']);
    $action->execute(exclusivePayload($agent, ['exclusive' => true]));
    $transition = Offer::latest('id')->first();
    expect($transition->exclusive)->toBeTrue()->and($transition->exclusive_transition)->toBeTrue();
    $action->execute(exclusivePayload($agent, ['exclusive' => true]));
    $duplicate = Offer::latest('id')->first();
    expect($duplicate->exclusive)->toBeTrue()->and($duplicate->exclusive_transition)->toBeFalse()
        ->and($duplicate->skip_reason)->toBe(ReceiveOfferWebhookAction::SKIP_REASON_DUPLICATE);
    $action->execute(exclusivePayload($agent, ['exclusive' => false]));
    expect(Offer::latest('id')->first()->exclusive)->toBeFalse();
});

it('treats the first exclusive snapshot as a normal scenario', function () {
    $agent = Agent::factory()->create();
    app(ReceiveOfferWebhookAction::class)->execute(exclusivePayload($agent, ['exclusive' => true]));
    $offer = Offer::latest('id')->first();
    expect($offer->exclusive_transition)->toBeFalse();
    app(ProcessOfferListener::class)->handle(new OfferCreatedEvent(null, $offer->id));
    expect($offer->fresh()->publication->scenario)->toBe(ScenarioType::SALE)
        ->and(ExclusivePublicationRun::count())->toBe(0);
});

it('records an empty run and counts it for the cooldown', function () {
    $offer = Offer::factory()->create(['exclusive' => true]);
    $source = Publication::factory()->forOffer($offer)->create();
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    expect($run->status)->toBe('empty')->and($run->publications()->count())->toBe(0);
    $next = Offer::factory()->create(['exclusive' => true, 'code' => $offer->code]);
    expect($service->start($next, $source, 'transition'))->toBeNull();
});

it('keeps a failed recipient independent and does not repeat successful tasks', function () {
    [$offer, $source] = exclusiveFixture();
    Offer::factory()->create(['city' => $offer->city]);
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    [$first, $second] = $run->publications()->orderBy('id')->get()->all();
    $first->tasks()->update(['status' => 'success']);
    $second->tasks()->where('type', Type::VK_UPLOAD_IMAGES)->update(['status' => 'success']);
    $second->tasks()->where('type', Type::VK_POST)->update(['status' => 'failed']);
    app(PublicationCompletionService::class)->check($second->id);
    expect($run->fresh()->status)->toBe('failed');
    expect(app(RetryPublicationTasksAction::class)->execute($first))->toBe(0);
    expect(app(RetryPublicationTasksAction::class)->execute($second))->toBe(2);
    expect($second->tasks()->where('type', Type::VK_UPLOAD_IMAGES)->first()->status)->toBe(Status::SUCCESS);
});

it('does not repeat a persisted post after a local failure', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    VkUser::factory()->create(['agent_id' => $recipient->id]);
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $task = $run->publications()->first()->tasks()->where('type', Type::VK_POST)->first();
    $task->update(['status' => Status::QUEUED]);
    VkWallPost::create(['offer_id' => $offer->id, 'task_id' => $task->id, 'owner_id' => $recipient->vkUser->vk_user_id, 'post_id' => 42]);
    $api = Mockery::mock(VkApiService::class);
    $api->shouldReceive('setToken')->once();
    $api->shouldNotReceive('wallPost');
    (new CreateVkPostJob($task->id))->handle($api, app(ScenarioVkPostTemplateResolver::class), app(PublicationTaskDependencyResolver::class), app(TaskDispatcher::class));
    expect($task->fresh()->status)->toBe(Status::SUCCESS);
});

it('recovers an existing story and uses its recipient context', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    VkUser::factory()->create(['agent_id' => $recipient->id, 'vk_user_id' => 98765]);
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $publication = $run->publications()->first();
    $postTask = $publication->tasks()->where('type', Type::VK_POST)->first();
    $post = VkWallPost::create(['offer_id' => $offer->id, 'task_id' => $postTask->id, 'owner_id' => 98765, 'post_id' => 42]);
    $postTask->update(['status' => Status::SUCCESS, 'external_id' => $post->id]);
    $story = $publication->tasks()->where('type', Type::VK_STORY)->first();
    $story->update(['status' => Status::QUEUED]);
    $context = app(VkStoriesContextFactory::class)->getContext($post->id);
    expect($context->agentName)->toBe($recipient->name)->and($context->postId)->toBe('98765_42');
    $api = Mockery::mock(VkApiService::class);
    $api->shouldReceive('setToken')->once();
    $api->shouldReceive('wallPostExists')->with('98765_42')->andReturnTrue();
    $api->shouldReceive('findStoryForPost')->with(98765, '98765_42')->andReturn(['owner_id' => 98765, 'id' => 99]);
    $api->shouldNotReceive('storiesPost');
    (new CreateVkStoriesJob($story->id))->handle($api, app(PublicationTaskDependencyResolver::class), app(TaskDispatcher::class));
    expect($story->fresh()->status)->toBe(Status::SUCCESS)->and($story->fresh()->external_id)->toBe('98765_99');
});

it('scopes admin retries to publications of the selected offer', function () {
    [$offer, $source] = exclusiveFixture();
    $other = Offer::factory()->create();
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $publication = $run->publications()->first();
    $this->withSession(['is_admin' => true])->post('/offers/'.$other->id.'/exclusive/retry', ['publication_id' => $publication->id])->assertNotFound();
    $this->post('/offers/'.$offer->id.'/exclusive/retry', ['publication_id' => $source->id])->assertNotFound();
});

it('attributes exclusive statistics to the recipient instead of the offer owner', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $task = $run->publications()->first()->tasks()->where('type', Type::VK_POST)->first();
    $post = VkWallPost::create(['offer_id' => $offer->id, 'task_id' => $task->id, 'owner_id' => 98765, 'post_id' => 42]);
    VkPostStat::create(['vk_post_id' => $post->id, 'views' => 123, 'reposts' => 0, 'likes' => 0, 'comments' => 0, 'datetime' => now()]);
    $stats = app(GetAgentPublicationStatsAction::class);
    expect($stats->execute($recipient->id)['statsByOffer'][$offer->code]['scenarios']['exclusive']['views'])->toBe(123)
        ->and($stats->execute($offer->agent_id)['statsByOffer'])->toBe([]);
});

it('does not blindly resend a story when an ambiguous attempt is older than a day', function () {
    [$offer, $source, $recipient] = exclusiveFixture();
    VkUser::factory()->create(['agent_id' => $recipient->id, 'vk_user_id' => 98765]);
    $service = app(ExclusivePublicationService::class);
    $run = $service->start($offer, $source, 'completion');
    $service->expand($run);
    $publication = $run->publications()->first();
    $postTask = $publication->tasks()->where('type', Type::VK_POST)->first();
    $post = VkWallPost::create(['offer_id' => $offer->id, 'task_id' => $postTask->id, 'owner_id' => 98765, 'post_id' => 42]);
    $postTask->update(['status' => Status::SUCCESS, 'external_id' => $post->id]);
    $story = $publication->tasks()->where('type', Type::VK_STORY)->first();
    $story->update(['status' => Status::QUEUED, 'external_started_at' => now()->subDays(2)]);
    $api = Mockery::mock(VkApiService::class);
    $api->shouldReceive('setToken')->once();
    $api->shouldReceive('wallPostExists')->andReturnTrue();
    $api->shouldReceive('findStoryForPost')->andReturnNull();
    $api->shouldNotReceive('storiesPost');
    (new CreateVkStoriesJob($story->id))->handle($api, app(PublicationTaskDependencyResolver::class), app(TaskDispatcher::class));
    expect($story->fresh()->status)->toBe(Status::FAILED)->and($story->fresh()->error)->toContain('вручную');
});

it('retries primary errors without creating another exclusive run', function () {
    [$offer, $source] = exclusiveFixture();
    $task = exclusiveTask($source, Status::FAILED);
    app(PublicationCompletionService::class)->check($source->id);
    app(RetryPublicationTasksAction::class)->execute($source);
    $task->update(['status' => Status::SUCCESS]);
    app(PublicationCompletionService::class)->check($source->id);
    expect(ExclusivePublicationRun::count())->toBe(1);
});
