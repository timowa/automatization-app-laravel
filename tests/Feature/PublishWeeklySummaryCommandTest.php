<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Deal;
use App\Enums\OfferStatus;
use App\Jobs\PublishWeeklySummaryJob;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\VkUser;
use App\Services\Llm\WeeklySummaryLlmGenerator;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PublishWeeklySummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeVkApiService $vkApi;

    private WeeklySummaryLlmGenerator $llmGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vkApi = new FakeVkApiService;
        $this->app->instance(VkApiService::class, $this->vkApi);

        $this->llmGenerator = Mockery::mock(WeeklySummaryLlmGenerator::class);
        $this->llmGenerator->shouldReceive('generate')
            ->andReturn('Сгенерированный текст поста');
        $this->app->instance(WeeklySummaryLlmGenerator::class, $this->llmGenerator);
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
        \App\Models\OfferImage::factory()->create(['offer_id' => $offer->id]);

        $job = new PublishWeeklySummaryJob($offer->agent_id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $wallPostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'wallPost');
        $this->assertCount(1, $wallPostCalls);
        $this->assertSame('wallPost', $wallPostCalls[array_key_first($wallPostCalls)]['method']);
        $this->assertSame((int) $vkUser->vk_user_id, $wallPostCalls[array_key_first($wallPostCalls)]['owner_id']);
        $this->assertSame('Сгенерированный текст поста', $wallPostCalls[array_key_first($wallPostCalls)]['message']);
    }

    public function test_job_limits_images_to_ten(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);

        for ($i = 0; $i < 12; $i++) {
            $offer = Offer::factory()->create([
                'agent_id' => $agent->id,
                'status' => OfferStatus::ACTIVE->value,
            ]);
            \App\Models\OfferImage::factory()->create([
                'offer_id' => $offer->id,
                'original_url' => "https://example.com/photo{$i}.jpg",
            ]);
        }

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $wallPostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'wallPost');
        $this->assertCount(1, $wallPostCalls);
    }

    public function test_job_counts_sold_and_rented_separately(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);

        $activeOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
        ]);
        \App\Models\OfferImage::factory()->create([
            'offer_id' => $activeOffer->id,
            'original_url' => 'https://example.com/active.jpg',
        ]);

        $soldCode = '100-200';
        Offer::factory()->count(2)->create([
            'agent_id' => $agent->id,
            'code' => $soldCode,
            'status' => OfferStatus::ARCHIVE->value,
            'deal' => Deal::SALE->value,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $rentedCode = '100-201';
        Offer::factory()->count(2)->create([
            'agent_id' => $agent->id,
            'code' => $rentedCode,
            'status' => OfferStatus::ARCHIVE->value,
            'deal' => Deal::RENT_OUT->value,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $wallPostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'wallPost');
        $this->assertCount(1, $wallPostCalls);
    }

    public function test_job_counts_active_sale_and_rent_separately(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);

        $saleOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
            'deal' => Deal::SALE->value,
        ]);
        \App\Models\OfferImage::factory()->create([
            'offer_id' => $saleOffer->id,
            'original_url' => 'https://example.com/active.jpg',
        ]);

        $rentOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
            'deal' => Deal::RENT_OUT->value,
        ]);
        \App\Models\OfferImage::factory()->create([
            'offer_id' => $rentOffer->id,
            'original_url' => 'https://example.com/rent.jpg',
        ]);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $wallPostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'wallPost');
        $this->assertCount(1, $wallPostCalls);
        $this->llmGenerator
            ->shouldHaveReceived('generate')
            ->with(
                Mockery::on(fn ($value) => $value instanceof Agent && $value->id === $agent->id),
                Mockery::on(fn ($value) => $value instanceof Carbon),
                Mockery::on(fn ($value) => $value instanceof Carbon),
                1,
                1,
                0,
                0
            );
    }

    public function test_job_skips_closed_count_when_zero(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        $offer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
        ]);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $wallPostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'wallPost');
        $this->assertCount(1, $wallPostCalls);
    }

    public function test_job_continues_on_vk_api_error(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        $offer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
        ]);
        \App\Models\OfferImage::factory()->create([
            'offer_id' => $offer->id,
            'original_url' => 'https://example.com/photo.jpg',
        ]);

        $this->vkApi->setFailNext('VKApiException', 'VK blocked');

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $this->assertGreaterThan(0, count($this->vkApi->calls));
    }

    public function test_job_does_not_publish_when_llm_fails(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        $offer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
        ]);
        \App\Models\OfferImage::factory()->create([
            'offer_id' => $offer->id,
            'original_url' => 'https://example.com/photo.jpg',
        ]);

        $failingLlmGenerator = Mockery::mock(WeeklySummaryLlmGenerator::class);
        $failingLlmGenerator->shouldReceive('generate')
            ->andThrow(new \RuntimeException('LLM недоступен'));
        $this->app->instance(WeeklySummaryLlmGenerator::class, $failingLlmGenerator);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi, $failingLlmGenerator);

        $wallPostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'wallPost');
        $this->assertCount(0, $wallPostCalls);
    }

    public function test_job_publishes_text_only_when_no_images(): void
    {
        $agent = Agent::factory()->create();
        VkUser::factory()->create(['agent_id' => $agent->id]);
        Offer::factory()->create([
            'agent_id' => $agent->id,
            'status' => OfferStatus::ACTIVE->value,
        ]);

        $job = new PublishWeeklySummaryJob($agent->id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $wallPostCalls = array_filter($this->vkApi->calls, fn ($call) => $call['method'] === 'wallPost');
        $this->assertCount(1, $wallPostCalls);
    }

    public function test_job_logs_and_returns_when_token_revoked_between_dispatch_and_handle(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->withoutToken()->create(['agent_id' => $offer->agent_id]);

        $job = new PublishWeeklySummaryJob($offer->agent_id);
        $job->handle($this->vkApi, $this->llmGenerator);

        $this->assertCount(0, $this->vkApi->calls);
    }
}
