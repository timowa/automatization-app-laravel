<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\TestWeeklySummaryCommand;
use App\Jobs\PublishWeeklySummaryJob;
use App\Models\Offer;
use App\Models\VkUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TestWeeklySummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_job_for_existing_agent(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        Queue::fake();

        $this->artisan('vk:test-summary', ['agentId' => $offer->agent_id])
            ->assertSuccessful();

        Queue::assertPushed(PublishWeeklySummaryJob::class, function ($job) use ($offer) {
            return $job->agentId === $offer->agent_id;
        });
    }

    public function test_command_fails_for_missing_agent(): void
    {
        Queue::fake();

        $this->artisan('vk:test-summary', ['agentId' => 9999])
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_command_fails_for_agent_without_token(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->withoutToken()->create(['agent_id' => $offer->agent_id]);
        Queue::fake();

        $this->artisan('vk:test-summary', ['agentId' => $offer->agent_id])
            ->assertFailed();

        Queue::assertNothingPushed();
    }
}
