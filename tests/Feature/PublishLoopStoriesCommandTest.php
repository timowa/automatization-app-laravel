<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferImage;
use App\Models\VkLoopStory;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishLoopStoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_publishes_only_stale_active_loop_stories(): void
    {
        if (! is_file(base_path('storage/app/assets/images/vkstory.png'))
            || ! is_file(base_path('storage/app/assets/fonts/ProximaNova.ttf'))) {
            $this->markTestSkipped('Нет файлов шаблона истории');
        }

        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        $post = VkWallPost::factory()->forOffer($offer->id)->create();

        $fresh = VkLoopStory::factory()->create([
            'offer_id' => $offer->id,
            'is_active' => true,
            'last_published_at' => Carbon::now()->subDay(),
        ]);
        OfferImage::factory()->create([
            'offer_id' => $offer->id,
            'original_url' => base_path('storage/app/assets/images/vkstory.png'),
        ]);

        $staleOffer = Offer::factory()->create();
        $stale = VkLoopStory::factory()->create([
            'offer_id' => $staleOffer->id,
            'is_active' => true,
            'last_published_at' => Carbon::now()->subDays(5),
        ]);
        VkUser::factory()->create(['agent_id' => $staleOffer->agent_id]);
        VkWallPost::factory()->forOffer($staleOffer->id)->create();
        OfferImage::factory()->create([
            'offer_id' => $staleOffer->id,
            'original_url' => base_path('storage/app/assets/images/vkstory.png'),
        ]);

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
        $this->assertNotEmpty(array_filter($vkApi->calls, fn (array $call): bool => $call['method'] === 'storiesPost'));

        $freshLastPublished = $fresh->last_published_at;
        $this->assertNotNull($freshLastPublished);
        $this->assertTrue($freshLastPublished->lessThan(Carbon::now()->subHour()));
    }

    public function test_command_postpones_story_without_photo_until_next_period(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        VkWallPost::factory()->forOffer($offer->id)->create();
        $story = VkLoopStory::factory()->create([
            'offer_id' => $offer->id,
            'is_active' => true,
            'last_published_at' => null,
        ]);

        $vkApi = new FakeVkApiService;
        $this->app->instance(VkApiService::class, $vkApi);

        $this->artisan('vk:publish-loop-stories')->assertSuccessful();

        $story = $story->fresh();
        $this->assertNotNull($story->last_published_at);
        $this->assertTrue($story->last_published_at->greaterThan(Carbon::now()->subMinute()));
        $this->assertTrue($story->is_active);
        $this->assertEmpty(array_filter($vkApi->calls, fn (array $call): bool => $call['method'] === 'storiesPost'));
    }

    public function test_command_postpones_loop_story_when_page_access_is_unavailable(): void
    {
        $offer = Offer::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $offer->agent_id,
            'is_token_available' => false,
            'vk_token' => 'revoked-token',
        ]);
        VkWallPost::factory()->forOffer($offer->id)->create();
        $story = VkLoopStory::factory()->create([
            'offer_id' => $offer->id,
            'is_active' => true,
            'last_published_at' => null,
        ]);

        $vkApi = new FakeVkApiService;
        $this->app->instance(VkApiService::class, $vkApi);

        $this->artisan('vk:publish-loop-stories')->assertSuccessful();

        $story = $story->fresh();
        $this->assertNotNull($story->last_published_at);
        $this->assertTrue($story->is_active);
        $this->assertEmpty(array_filter($vkApi->calls, fn (array $call): bool => $call['method'] === 'storiesPost'));
    }

    public function test_loop_stories_command_is_scheduled_once_a_day(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'vk:publish-loop-stories'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('0 10 * * *', $events[0]->expression);
    }

    public function test_command_retries_connection_timeout_and_keeps_story_due(): void
    {
        if (! is_file(base_path('storage/app/assets/images/vkstory.png'))
            || ! is_file(base_path('storage/app/assets/fonts/ProximaNova.ttf'))) {
            $this->markTestSkipped('Нет файлов шаблона истории');
        }

        $offer = Offer::factory()->create();
        VkUser::factory()->create(['agent_id' => $offer->agent_id]);
        VkWallPost::factory()->forOffer($offer->id)->create();
        OfferImage::factory()->create([
            'offer_id' => $offer->id,
            'original_url' => base_path('storage/app/assets/images/vkstory.png'),
        ]);
        $story = VkLoopStory::factory()->create([
            'offer_id' => $offer->id,
            'is_active' => true,
            'last_published_at' => null,
        ]);

        $vkApi = new FakeVkApiService;
        $vkApi->setFailNext(
            'RuntimeException',
            'cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received',
            1,
            3,
        );
        $this->app->instance(VkApiService::class, $vkApi);

        $this->artisan('vk:publish-loop-stories')->assertSuccessful();

        $story = $story->fresh();
        $this->assertNull($story->last_published_at);
        $this->assertTrue($story->is_active);
        $calls = array_values(array_filter(
            $vkApi->calls,
            fn (array $call): bool => $call['method'] === 'storiesPost',
        ));
        $this->assertCount(3, $calls);
    }
}
