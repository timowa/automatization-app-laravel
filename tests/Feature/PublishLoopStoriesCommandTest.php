<?php

namespace Tests\Feature;

use App\Console\Commands\PublishLoopStoriesCommand;
use App\Models\Offer;
use App\Models\VkLoopStory;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
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
        $offer->update(['images' => [base_path('storage/app/assets/images/vkstory.png')]]);

        $staleOffer = Offer::factory()->create();
        $stale = VkLoopStory::factory()->create([
            'offer_id' => $staleOffer->id,
            'is_active' => true,
            'last_published_at' => Carbon::now()->subDays(5),
        ]);
        VkUser::factory()->create(['agent_id' => $staleOffer->agent_id]);
        VkWallPost::factory()->forOffer($staleOffer->id)->create();
        $staleOffer->update(['images' => [base_path('storage/app/assets/images/vkstory.png')]]);

        $inactive = VkLoopStory::factory()->inactive()->create();

        $vkApi = new FakeVkApiService;
        $vkApi->storiesPostResponse = ['count' => 1];
        $this->app->instance(VkApiService::class, $vkApi);

        $this->artisan('vk:publish-loop-stories')
            ->assertSuccessful();

        $stale = $stale->fresh();
        $fresh = $fresh->fresh();

        $this->assertNotNull($stale->last_published_at);
        $this->assertTrue($stale->last_published_at->greaterThan(Carbon::now()->subMinute()));

        $freshLastPublished = $fresh->last_published_at;
        $this->assertNotNull($freshLastPublished);
        $this->assertTrue($freshLastPublished->lessThan(Carbon::now()->subHour()));
    }
}
