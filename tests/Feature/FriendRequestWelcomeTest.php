<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\FriendRequestWelcomeText;
use App\Jobs\ProcessFriendRequestWelcomeJob;
use App\Jobs\SyncAgentFriendRequestsJob;
use App\Models\Agent;
use App\Models\AgentVkFriendRequest;
use App\Models\AgentWelcomeCard;
use App\Models\Setting;
use App\Models\VkUser;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FriendRequestWelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_welcome_text_contains_placeholders(): void
    {
        $text = FriendRequestWelcomeText::resolveTemplate(null);

        $this->assertNotNull($text);
        $this->assertStringContainsString('{{agent_name}}', $text);
        $this->assertStringContainsString('{{brokerplus}}', $text);
    }

    public function test_photo_attachment_uses_owner_and_media_id(): void
    {
        $this->assertSame('photo302572235_12345', FriendRequestWelcomeText::photoAttachment(302572235, 12345));
    }

    public function test_sync_job_inserts_only_active_open_new_requests(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'friend_request_welcome_enabled' => true,
        ]);
        AgentVkFriendRequest::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 100,
            'first_name' => 'Уже',
            'last_name' => 'Есть',
            'is_closed' => false,
            'payload' => ['user_id' => 100],
        ]);

        $fake = new FakeVkApiService;
        $fake->friendRequestsResponse = [
            [
                'user_id' => 100,
                'first_name' => 'Уже',
                'last_name' => 'Есть',
                'is_closed' => false,
                'can_access_closed' => true,
            ],
            [
                'user_id' => 200,
                'first_name' => 'Новый',
                'last_name' => 'Активный',
                'is_closed' => false,
                'can_access_closed' => true,
                'mutual' => ['count' => 0, 'users' => []],
            ],
            [
                'user_id' => 300,
                'first_name' => 'Закрытый',
                'last_name' => 'Профиль',
                'is_closed' => true,
                'can_access_closed' => false,
            ],
            [
                'user_id' => 400,
                'first_name' => 'Бан',
                'last_name' => 'Аккаунт',
                'is_closed' => false,
                'deactivated' => 'banned',
            ],
        ];

        (new SyncAgentFriendRequestsJob($agent->id))->handle($fake);

        $this->assertDatabaseHas('agent_vk_friend_requests', [
            'agent_id' => $agent->id,
            'user_id' => 200,
            'first_name' => 'Новый',
            'message_id' => null,
        ]);
        $this->assertDatabaseMissing('agent_vk_friend_requests', [
            'agent_id' => $agent->id,
            'user_id' => 300,
        ]);
        $this->assertDatabaseMissing('agent_vk_friend_requests', [
            'agent_id' => $agent->id,
            'user_id' => 400,
        ]);
        $this->assertSame(2, AgentVkFriendRequest::query()->where('agent_id', $agent->id)->count());
    }

    public function test_process_job_marks_sentinel_when_agent_already_wrote(): void
    {
        $agent = Agent::factory()->create(['name' => 'Агент']);
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'vk_user_id' => 111,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'friend_request_welcome_enabled' => true,
        ]);
        $request = AgentVkFriendRequest::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 222,
            'first_name' => 'Подписчик',
            'is_closed' => false,
            'payload' => ['user_id' => 222],
        ]);

        $fake = new FakeVkApiService;
        $fake->hasOutgoingMessage = true;

        (new ProcessFriendRequestWelcomeJob($request->id))->handle($fake);

        $request->refresh();
        $this->assertSame(AgentVkFriendRequest::AGENT_ALREADY_MESSAGED_SENTINEL, $request->message_id);
        $this->assertFalse(collect($fake->calls)->contains(
            fn (array $call): bool => $call['method'] === 'sendDirectMessage'
        ));
    }

    public function test_process_job_sends_message_with_card_attachment(): void
    {
        $agent = Agent::factory()->create(['name' => 'Мария']);
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'vk_user_id' => 555001,
            'is_token_available' => true,
            'vk_token' => 'token',
            'first_name' => 'Мария',
            'last_name' => 'Иванова',
        ]);
        $card = AgentWelcomeCard::query()->create([
            'agent_id' => $agent->id,
            'path' => 'agent-cards/agent_'.$agent->id.'_card.jpg',
            'thumb_path' => 'agent-cards/agent_'.$agent->id.'_card_thumb.jpg',
            'media_id' => 99887,
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'friend_request_welcome_enabled' => true,
            'friend_request_welcome_text' => null,
            'friend_request_card_id' => $card->id,
        ]);
        $request = AgentVkFriendRequest::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 777,
            'first_name' => 'Новый',
            'is_closed' => false,
            'payload' => ['user_id' => 777],
        ]);

        $fake = new FakeVkApiService;
        $fake->hasOutgoingMessage = false;

        (new ProcessFriendRequestWelcomeJob($request->id))->handle($fake);

        $request->refresh();
        $this->assertNotNull($request->message_id);
        $this->assertGreaterThan(1, $request->message_id);

        $sendCall = collect($fake->calls)->first(
            fn (array $call): bool => $call['method'] === 'sendDirectMessage'
        );
        $this->assertNotNull($sendCall);
        $this->assertSame(777, $sendCall['user_id']);
        $this->assertStringContainsString('Мария', $sendCall['message']);
        $this->assertSame('photo555001_99887', $sendCall['attachment']);
    }

    public function test_sync_command_dispatches_jobs_for_enabled_agents(): void
    {
        Queue::fake();

        $enabled = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $enabled->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $enabled->id,
            'friend_request_welcome_enabled' => true,
        ]);

        $disabled = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $disabled->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $disabled->id,
            'friend_request_welcome_enabled' => false,
        ]);

        $this->artisan('vk:sync-friend-requests')->assertSuccessful();

        Queue::assertPushed(SyncAgentFriendRequestsJob::class, function (SyncAgentFriendRequestsJob $job) use ($enabled): bool {
            return $job->agentId === $enabled->id;
        });
        Queue::assertNotPushed(SyncAgentFriendRequestsJob::class, function (SyncAgentFriendRequestsJob $job) use ($disabled): bool {
            return $job->agentId === $disabled->id;
        });
    }

    public function test_welcome_card_upload_saves_card_table_and_thumb(): void
    {
        Storage::fake('public');

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'vk_user_id' => 42,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create(['agent_id' => $agent->id]);

        $fake = new FakeVkApiService;
        $fake->uploadMessagesPhotoId = 7777;
        $this->app->instance(VkApiService::class, $fake);

        $file = UploadedFile::fake()->image('card.jpg', 1200, 600);

        $response = $this->withSession(['is_admin' => true])->postJson('/agents/settings/'.$agent->id.'/card', [
            'card' => $file,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'has_card' => true,
            'friend_request_card_media_id' => 7777,
        ]);

        $card = AgentWelcomeCard::query()->where('agent_id', $agent->id)->first();
        $this->assertNotNull($card);
        $this->assertSame(7777, $card->media_id);
        $this->assertSame('agent-cards/agent_'.$agent->id.'_card.jpg', $card->path);
        $this->assertSame('agent-cards/agent_'.$agent->id.'_card_thumb.jpg', $card->thumb_path);

        $this->assertDatabaseHas('settings', [
            'agent_id' => $agent->id,
            'friend_request_card_id' => $card->id,
        ]);
        Storage::disk('public')->assertExists($card->path);
        Storage::disk('public')->assertExists($card->thumb_path);
    }

    public function test_welcome_card_upload_failure_does_not_save_card(): void
    {
        Storage::fake('public');

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create(['agent_id' => $agent->id]);

        $fake = new FakeVkApiService;
        $fake->setFailNext('VKApiException', 'upload failed', 1);
        $this->app->instance(VkApiService::class, $fake);

        $response = $this->withSession(['is_admin' => true])->postJson('/agents/settings/'.$agent->id.'/card', [
            'card' => UploadedFile::fake()->image('card.png'),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseHas('settings', [
            'agent_id' => $agent->id,
            'friend_request_card_id' => null,
        ]);
        $this->assertSame(0, AgentWelcomeCard::query()->where('agent_id', $agent->id)->count());
        Storage::disk('public')->assertMissing('agent-cards/agent_'.$agent->id.'_card.png');
        Storage::disk('public')->assertMissing('agent-cards/agent_'.$agent->id.'_card_thumb.png');
    }

    public function test_permanent_privacy_refusal_is_skipped_by_next_welcome_command(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'friend_request_welcome_enabled' => true,
            'friend_request_welcome_text' => 'Здравствуйте',
        ]);

        $blocked = AgentVkFriendRequest::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 600741496,
            'first_name' => 'Закрытый',
            'is_closed' => false,
            'payload' => ['user_id' => 600741496],
        ]);
        $next = AgentVkFriendRequest::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 600741497,
            'first_name' => 'Следующий',
            'is_closed' => false,
            'payload' => ['user_id' => 600741497],
        ]);

        $fake = new FakeVkApiService;
        $fake->setFailNext('RuntimeException', "Can't send messages to this user due to their privacy settings");

        (new ProcessFriendRequestWelcomeJob($blocked->id))->handle($fake);

        $blocked->refresh();
        $this->assertNull($blocked->message_id);
        $this->assertTrue($blocked->welcome_permanently_failed);

        Queue::fake();
        $this->artisan('vk:process-friend-request-welcome')->assertSuccessful();

        Queue::assertPushed(ProcessFriendRequestWelcomeJob::class, function (ProcessFriendRequestWelcomeJob $job) use ($next): bool {
            return $job->friendRequestId === $next->id;
        });
        Queue::assertNotPushed(ProcessFriendRequestWelcomeJob::class, function (ProcessFriendRequestWelcomeJob $job) use ($blocked): bool {
            return $job->friendRequestId === $blocked->id;
        });
    }

    public function test_temporary_welcome_failure_stays_in_queue(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'friend_request_welcome_enabled' => true,
            'friend_request_welcome_text' => 'Здравствуйте',
        ]);
        $request = AgentVkFriendRequest::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 42,
            'first_name' => 'Временный',
            'is_closed' => false,
            'payload' => ['user_id' => 42],
        ]);

        $fake = new FakeVkApiService;
        $fake->setFailNext('RuntimeException', 'Operation timed out after 15000 milliseconds');

        (new ProcessFriendRequestWelcomeJob($request->id))->handle($fake);

        $request->refresh();
        $this->assertNull($request->message_id);
        $this->assertFalse($request->welcome_permanently_failed);

        Queue::fake();
        $this->artisan('vk:process-friend-request-welcome')->assertSuccessful();

        Queue::assertPushed(ProcessFriendRequestWelcomeJob::class, function (ProcessFriendRequestWelcomeJob $job) use ($request): bool {
            return $job->friendRequestId === $request->id;
        });
    }
}
