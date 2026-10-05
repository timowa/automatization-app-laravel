<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\GetAgentPublicationStatsAction;
use App\Enums\ScenarioType;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Models\VkPostStat;
use App\Models\VkWallPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentPublicationStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_uses_only_the_latest_snapshot_per_post(): void
    {
        $agent = Agent::factory()->create();
        $offer = Offer::factory()->withAgent($agent->id)->withCode('219-100')->create();

        $this->createPostStat($offer, ScenarioType::SALE, [
            'views' => 10,
            'reposts' => 1,
            'likes' => 1,
            'comments' => 1,
        ]);
        $this->createPostStat($offer, ScenarioType::SALE, [
            'views' => 40,
            'reposts' => 4,
            'likes' => 5,
            'comments' => 6,
        ], samePost: true);
        $this->createPostStat($offer, ScenarioType::PRICE_CHANGED, [
            'views' => 7,
            'reposts' => 1,
            'likes' => 1,
            'comments' => 0,
        ]);

        $otherOffer = Offer::factory()->withCode('999-1')->create();
        $this->createPostStat($otherOffer, ScenarioType::SALE, [
            'views' => 999,
            'reposts' => 9,
            'likes' => 9,
            'comments' => 9,
        ]);

        $stats = app(GetAgentPublicationStatsAction::class)->execute($agent->id);

        $this->assertSame([
            'views' => 47,
            'reposts' => 5,
            'likes' => 6,
            'comments' => 6,
        ], $stats['statsByOffer']['219-100']['total']);
        $this->assertSame(40, $stats['statsByOffer']['219-100']['scenarios']['sale']['views']);
        $this->assertSame(7, $stats['statsByOffer']['219-100']['scenarios']['price_changed']['views']);
        $this->assertArrayNotHasKey('999-1', $stats['statsByOffer']);

        $response = $this->withSession(['is_admin' => true])->get('/agents/edit/'.$agent->id);

        $response->assertOk();
        $response->assertSee('219-100');
        $response->assertSee('Продажа');
        $response->assertSee('Изменилась цена');
        $response->assertDontSee('999-1');
    }

    /**
     * @param  array{views: int, reposts: int, likes: int, comments: int}  $stat
     */
    private function createPostStat(Offer $offer, ScenarioType $scenario, array $stat, bool $samePost = false): void
    {
        static $posts = [];

        $key = $offer->id.'-'.$scenario->value;
        if ($samePost && isset($posts[$key])) {
            VkPostStat::create([
                'vk_post_id' => $posts[$key],
                ...$stat,
            ]);

            return;
        }

        $publication = Publication::factory()->forOffer($offer)->withScenario($scenario)->create();
        $task = PublicationTask::factory()->create(['publication_id' => $publication->id]);
        $post = VkWallPost::factory()->forOffer($offer->id)->forTask($task->id)->create();
        $posts[$key] = $post->id;

        VkPostStat::create([
            'vk_post_id' => $post->id,
            ...$stat,
        ]);
    }
}
