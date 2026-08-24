<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\PublishWeeklySummaryCommand;
use App\Enums\OfferStatus;
use App\Jobs\PublishWeeklySummaryJob;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\VkUser;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishWeeklySummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeVkApiService $vkApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vkApi = new FakeVkApiService;
        $this->app->instance(VkApiService::class, $this->vkApi);
    }

    public function test_command_skips_agent_without_vk_user(): void
    {
        Offer::factory()->create();
        Queue::fake();

        $this->artisan('vk:publish-weekly-summary')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_command_skips_agent_without_token(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->withoutToken()->create(['agent_id' => $offer->agent_id]);
        Queue::fake();

        $this->artisan('vk:publish-weekly-summary')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_command_skips_agent_without_active_offers(): void
    {
        $offer = Offer::factory()->withStatus(OfferStatus::ARCHIVE->value)->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        Queue::fake();

        $this->artisan('vk:publish-weekly-summary')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_command_dispatches_job_for_agent_with_active_offers(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        Queue::fake();

        $this->artisan('vk:publish-weekly-summary')
            ->assertSuccessful();

        Queue::assertPushed(PublishWeeklySummaryJob::class, function ($job) use ($offer) {
            return $job->agentId === $offer->agent_id;
        });
    }

    public function test_command_dispatches_job_with_delay_between_10_and_60_minutes(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        Offer::factory()->create(['agent_id' => $agent->id]);
        Queue::fake();

        $this->artisan('vk:publish-weekly-summary')
            ->assertSuccessful();

        Queue::assertPushed(PublishWeeklySummaryJob::class, function ($job) {
            return $job->delay >= now()->addMinutes(10)
                && $job->delay <= now()->addMinutes(60);
        });
    }

    public function test_job_publishes_post_for_agent(): void
    {
        $offer = Offer::factory()->create();
        $vkUser = VkUser::factory()->create(['agent_id' => $offer->agent_id]);

        $job = new PublishWeeklySummaryJob($offer->agent_id);
        $job->handle($this->vkApi);

        $this->assertCount(1, $this->vkApi->calls);
        $this->assertSame('wallPost', $this->vkApi->calls[0]['method']);
        $this->assertSame((int) $vkUser->vk_user_id, $this->vkApi->calls[0]['owner_id']);
    }

    public function test_job_limits_images_to_ten(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);

        for ($i = 0; $i < 12; $i++) {
            Offer::factory()->create([
                'agent_id' => $agent->id,
                'images' => ["https://example.com/photo{$i}.jpg"],
                'status' => OfferStatus::ACTIVE->value,
            ]);
        }

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi);

        $this->assertCount(1, $this->vkApi->calls);
    }

    public function test_job_counts_unique_closed_codes(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);

        Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
            'images' => ['https://example.com/active.jpg'],
        ]);

        $code = '100-200';
        Offer::factory()->count(2)->create([
            'agent_id' => $agent->id,
            'code' => $code,
            'status' => OfferStatus::ARCHIVE->value,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi);

        $this->assertSame('wallPost', $this->vkApi->calls[0]['method']);
        $this->assertStringContainsString('На этой неделе продано: 1', $this->vkApi->calls[0]['message'] ?? '');
    }

    public function test_job_skips_closed_count_when_zero(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
            'images' => [],
        ]);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi);

        $this->assertCount(1, $this->vkApi->calls);
        $this->assertStringNotContainsString('продано', $this->vkApi->calls[0]['message'] ?? '');
    }

    public function test_job_continues_on_vk_api_error(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
            'images' => ['https://example.com/photo.jpg'],
        ]);

        $this->vkApi->setFailNext('VKApiException', 'VK blocked');

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi);

        $this->assertCount(1, $this->vkApi->calls);
    }

    public function test_job_publishes_text_only_when_no_images(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
            'images' => [],
        ]);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi);

        $this->assertCount(1, $this->vkApi->calls);
        $this->assertSame('wallPost', $this->vkApi->calls[0]['method']);
    }

    public function test_job_logs_and_returns_when_token_revoked_between_dispatch_and_handle(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->withoutToken()->create(['agent_id' => $offer->agent_id]);

        $job = new PublishWeeklySummaryJob($offer->agent_id);
        $job->handle($this->vkApi);

        $this->assertCount(0, $this->vkApi->calls);
    }
}
