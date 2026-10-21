<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\City;
use App\Enums\Deal;
use App\Enums\OfferStatus;
use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Jobs\CreateVkPostJob;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Services\OfferHealthStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OffersAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_offers_page_lists_and_filters(): void
    {
        $agent = Agent::factory()->create(['name' => 'Тест Агент']);
        $other = Agent::factory()->create();

        Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '111-001',
            'city' => City::KYZYL->value,
            'deal' => Deal::SALE->value,
            'status' => OfferStatus::ACTIVE->value,
        ]);
        Offer::factory()->create([
            'agent_id' => $other->id,
            'code' => '222-002',
            'city' => City::ABAKAN->value,
            'deal' => Deal::RENT->value,
            'status' => OfferStatus::BOOKED->value,
        ]);

        $response = $this->withSession(['is_admin' => true])
            ->get('/offers?agent_id='.$agent->id.'&code=111');

        $response->assertOk();
        $response->assertSee('111-001');
        $response->assertDontSee('222-002');
        $response->assertSee('Офферы');
    }

    public function test_offers_page_sorts_by_code_and_column_order(): void
    {
        $agentA = Agent::factory()->create(['name' => 'Агент А']);
        $agentB = Agent::factory()->create(['name' => 'Агент Б']);

        Offer::factory()->create(['agent_id' => $agentB->id, 'code' => 'zzz-999', 'price' => 100]);
        Offer::factory()->create(['agent_id' => $agentA->id, 'code' => 'aaa-001', 'price' => 200]);

        $response = $this->withSession(['is_admin' => true])
            ->get('/offers?sort=code&dir=asc');

        $response->assertOk();
        $response->assertSeeInOrder(['aaa-001', 'zzz-999']);
        $content = $response->getContent();
        $codePos = strpos($content, 'sort=code');
        $agentPos = strpos($content, 'sort=agent');
        $scenarioPos = strpos($content, 'sort=scenario');
        $this->assertNotFalse($codePos);
        $this->assertNotFalse($agentPos);
        $this->assertNotFalse($scenarioPos);
        $this->assertTrue($codePos < $agentPos && $agentPos < $scenarioPos);
    }

    public function test_health_resolver_ok_and_warning(): void
    {
        $resolver = new OfferHealthStatusResolver;

        $okOffer = Offer::factory()->create(['skip_reason' => null]);
        $publication = Publication::factory()->forOffer($okOffer)->withScenario(ScenarioType::SALE)->create();
        PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();

        $ok = $resolver->resolve($okOffer->fresh(['publication.tasks']));
        $this->assertSame(OfferHealthStatusResolver::STATUS_OK, $ok['status']);
        $this->assertFalse($ok['can_retry']);

        $skipped = Offer::factory()->create(['skip_reason' => 'Не найден агент по номеру телефона']);
        $warn = $resolver->resolve($skipped);
        $this->assertSame(OfferHealthStatusResolver::STATUS_WARNING, $warn['status']);
        $this->assertSame('Не найден агент по номеру телефона', $warn['tooltip']);
        $this->assertFalse($warn['can_retry']);

        $failedOffer = Offer::factory()->create(['skip_reason' => null]);
        $failedPub = Publication::factory()->forOffer($failedOffer)->withScenario(ScenarioType::SALE)->create();
        PublicationTask::factory()
            ->for($failedPub)
            ->ofType(PublicationTaskType::VK_REPOST)
            ->withStatus(PublicationTaskStatus::FAILED)
            ->create(['error' => 'Flood control']);

        $failed = $resolver->resolve($failedOffer->fresh(['publication.tasks']));
        $this->assertSame(OfferHealthStatusResolver::STATUS_WARNING, $failed['status']);
        $this->assertTrue($failed['can_retry']);
        $this->assertStringContainsString('Репост не выполнен: flood control', $failed['tooltip']);
    }

    public function test_retry_queues_failed_tasks(): void
    {
        Queue::fake();

        $offer = Offer::factory()->create();
        $publication = Publication::factory()->forOffer($offer)->withScenario(ScenarioType::SALE)->create();
        $failed = PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::FAILED)
            ->create(['error' => 'fail']);
        PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_COMMENT)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();

        $response = $this->withSession(['is_admin' => true])
            ->postJson('/offers/'.$offer->id.'/retry');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'retried' => 1,
            'status' => OfferHealthStatusResolver::STATUS_PROCESSING,
            'can_retry' => false,
        ]);
        $this->assertNotEmpty($response->json('tasks'));
        $this->assertSame('queued', $response->json('tasks.0.status'));

        $failed->refresh();
        $this->assertSame(PublicationTaskStatus::QUEUED, $failed->status);
        $this->assertNull($failed->error);
        Queue::assertPushed(CreateVkPostJob::class);
    }

    public function test_status_endpoint_returns_warning_with_retry_after_failure(): void
    {
        $offer = Offer::factory()->create();
        $publication = Publication::factory()->forOffer($offer)->withScenario(ScenarioType::SALE)->create();
        PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_POST)
            ->withStatus(PublicationTaskStatus::FAILED)
            ->create(['error' => 'Flood control']);

        $response = $this->withSession(['is_admin' => true])
            ->getJson('/offers/'.$offer->id.'/status');

        $response->assertOk();
        $response->assertJson([
            'status' => OfferHealthStatusResolver::STATUS_WARNING,
            'can_retry' => true,
        ]);
        $this->assertStringContainsString('flood control', (string) $response->json('tooltip'));
    }

    public function test_tasks_endpoint_returns_labels_and_ui_date(): void
    {
        $offer = Offer::factory()->create();
        $publication = Publication::factory()->forOffer($offer)->withScenario(ScenarioType::SALE)->create();
        PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_STORY)
            ->withStatus(PublicationTaskStatus::SUCCESS)
            ->create();
        PublicationTask::factory()
            ->for($publication)
            ->ofType(PublicationTaskType::VK_REPOST)
            ->withStatus(PublicationTaskStatus::FAILED)
            ->create(['error' => 'Flood control']);

        $response = $this->withSession(['is_admin' => true])
            ->getJson('/offers/'.$offer->id.'/tasks');

        $response->assertOk();
        $response->assertJsonPath('tasks.0.title', 'Публикация истории');
        $response->assertJsonPath('tasks.0.tooltip', 'Успешно');
        $response->assertJsonPath('tasks.1.tooltip', 'flood control');
        $this->assertMatchesRegularExpression('/^\d{2}-\d{2}-\d{4}$/', $response->json('tasks.0.finished_at'));
    }

    public function test_posts_route_removed(): void
    {
        $this->withSession(['is_admin' => true])->get('/posts')->assertNotFound();
    }
}
