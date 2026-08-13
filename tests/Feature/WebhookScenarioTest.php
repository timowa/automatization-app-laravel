<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function payload(array $overrides = []): array
    {
        $this->withHeader('Authorization', 'Bearer ' . config('app.api_key'));

        return [
            'offers' => [
                array_merge([
                    'id' => 53226,
                    'code' => '217-100',
                    'created' => 1784707652,
                    'updated' => 1784707652,
                    'url' => 'https://kyzyl.brokerplus.ru/prodazha-kvartir/53226',
                    'category' => 'квартира',
                    'deal' => 'продажа',
                    'status' => 'актив',
                    'stage' => 3,
                    'price' => 695000,
                    'rooms' => 2,
                    'floor' => null,
                    'floors' => 5,
                    'totalArea' => '51.6',
                    'kitchenArea' => 10,
                    'livingArea' => 35,
                    'location' => [
                        'city' => 'Кызыл',
                        'address' => 'ул. Калинина, 24',
                    ],
                    'agent' => [
                        'name' => 'Барби Маргарита Викторовна',
                        'phone' => '+79953742476',
                        'organization' => null,
                    ],
                    'photos' => [
                        ['url' => 'https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/dfe758a04f96e498c650e184f376ebf0.jpg'],
                    ],
                    'videos' => null,
                ], $overrides),
            ],
        ];
    }

    public function test_new_offer_without_price_triggers_announcement_scenario(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload(['price' => 0]));

        $response->assertOk();
        $this->assertDatabaseCount('offers', 1);

        $offer = Offer::first();
        $this->assertNotNull($offer);

        $publication = Publication::where('offer_id', $offer->id)->first();
        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::ANNOUNCEMENT->value, $publication->scenario->value);

        $this->assertDatabaseHas('publication_tasks', [
            'publication_id' => $publication->id,
            'type' => PublicationTaskType::VK_POST->value,
            'status' => PublicationTaskStatus::PENDING->value,
        ]);
    }

    public function test_active_offer_with_price_triggers_sale_scenario(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload());

        $response->assertOk();
        $offer = Offer::first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::SALE->value, $publication->scenario->value);

        $types = PublicationTask::where('publication_id', $publication->id)
            ->pluck('type')
            ->toArray();

        $this->assertContains(PublicationTaskType::VK_POST->value, $types);
        $this->assertContains(PublicationTaskType::VK_LOOP_STORY->value, $types);
        $this->assertContains(PublicationTaskType::VK_CREATE_PRODUCT->value, $types);
    }

    public function test_price_decrease_triggers_price_changed_scenario(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        Publication::factory()->forOffer($prevOffer)->withScenario(ScenarioType::SALE)->create();

        $response = $this->postJson('/offer', $this->payload(['price' => 900_000]));

        $response->assertOk();
        $offer = Offer::latest('id')->first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::PRICE_CHANGED->value, $publication->scenario->value);
    }

    public function test_small_price_decrease_does_not_trigger_price_changed_scenario(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        Publication::factory()->forOffer($prevOffer)->withScenario(ScenarioType::SALE)->create();

        $response = $this->postJson('/offer', $this->payload(['price' => 995_000]));

        $response->assertOk();
        $offer = Offer::latest('id')->first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNull($publication);
    }

    public function test_agent_change_triggers_agent_changed_scenario(): void
    {
        $firstAgent = Agent::factory()->create(['phone' => '79953742476']);
        $secondAgent = Agent::factory()->create(['phone' => '79999999999']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $firstAgent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        Publication::factory()->forOffer($prevOffer)->withScenario(ScenarioType::SALE)->create();

        $response = $this->postJson('/offer', $this->payload([
            'agent' => ['name' => 'Second Agent', 'phone' => '+79999999999'],
        ]));

        $response->assertOk();
        $offer = Offer::latest('id')->first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::AGENT_CHANGED->value, $publication->scenario->value);
        $this->assertSame($secondAgent->id, $offer->agent_id);
    }

    public function test_archive_status_triggers_sold_then_feedback(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        Publication::factory()->forOffer($prevOffer)->withScenario(ScenarioType::SALE)->create();

        $this->postJson('/offer', $this->payload(['status' => 'архив']));
        $firstArchive = Offer::latest('id')->first();
        $firstPublication = Publication::where('offer_id', $firstArchive->id)->first();
        $this->assertNotNull($firstPublication);
        $this->assertSame(ScenarioType::SOLD->value, $firstPublication->scenario->value);

        $this->postJson('/offer', $this->payload(['status' => 'архив']));
        $secondArchive = Offer::latest('id')->first();
        $secondPublication = Publication::where('offer_id', $secondArchive->id)->first();
        $this->assertNotNull($secondPublication);
        $this->assertSame(ScenarioType::FEEDBACK->value, $secondPublication->scenario->value);
    }

    public function test_duplicate_offer_is_skipped(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 695000,
            'stage' => 3,
        ]);

        $response = $this->postJson('/offer', $this->payload());

        $response->assertOk();
        $this->assertDatabaseCount('offers', 1);
    }

    public function test_unknown_agent_logs_warning_and_returns_ok(): void
    {
        $response = $this->postJson('/offer', $this->payload());

        $response->assertOk();
        $this->assertDatabaseCount('offers', 0);
    }
}
