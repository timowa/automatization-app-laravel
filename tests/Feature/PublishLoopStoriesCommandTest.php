<?php

namespace Tests\Feature;

use App\Console\Commands\PublishLoopStoriesCommand;
use App\Models\Offer;
use App\Models\VkLoopStory;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\FakeVkApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishLoopStoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_publishes_only_stale_active_loop_stories(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $post = VkWallPost::factory()->forOffer($offer->id)->create();

        $fresh = VkLoopStory::factory()->create([
            'offer_id' => $offer->id,
            'is_active' => true,
            'last_published_at' => Carbon::now()->subDay(),
        ]);

        $stale = VkLoopStory::factory()->create([
            'offer_id' => Offer::factory(),
            'is_active' => true,
            'last_published_at' => Carbon::now()->subDays(5),
        ]);
        VkUser::factory()->create(['agent_id' => $stale->offer->agent_id]);
        VkWallPost::factory()->forOffer($stale->offer->id)->create();

        $inactive = VkLoopStory::factory()->inactive()->create();

        $vkApi = new FakeVkApiService;
        $command = new PublishLoopStoriesCommand;

        $this->artisan('vk:publish-loop-stories')
            ->assertSuccessful();

        $stale->fresh();
        $fresh->fresh();

        $this->assertNotNull($stale->last_published_at);
        $this->assertTrue($stale->last_published_at->greaterThan(Carbon::now()->subMinute()));

        $freshLastPublished = $fresh->last_published_at;
        $this->assertNotNull($freshLastPublished);
        $this->assertTrue($freshLastPublished->lessThan(Carbon::now()->subHour()));
    }
}
