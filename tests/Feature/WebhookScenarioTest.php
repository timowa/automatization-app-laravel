<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Enums\City;
use App\Enums\Deal;
use App\Enums\OfferStatus;
use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Helpers\JobResolver;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Jobs\ArchiveVkProductJob;
use App\Jobs\CreateVkCommentJob;
use App\Jobs\CreateVkLikeJob;
use App\Jobs\CreateVkLoopStoryJob;
use App\Jobs\CreateVkPostJob;
use App\Jobs\CreateVkProductJob;
use App\Jobs\CreateVkRepostJob;
use App\Jobs\CreateVkStoriesJob;
use App\Jobs\EditVkProductJob;
use App\Jobs\EndVkLoopStoryJob;
use App\Jobs\UploadVkImagesJob;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\OfferImage;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Models\VkLoopStory;
use App\Scenarios\TaskDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Фейкаем только VK-джобы: ProcessOfferListener (ShouldQueue) должен выполниться.
        Queue::fake([
            UploadVkImagesJob::class,
            CreateVkPostJob::class,
            CreateVkRepostJob::class,
            CreateVkStoriesJob::class,
            CreateVkLoopStoryJob::class,
            EndVkLoopStoryJob::class,
            CreateVkCommentJob::class,
            CreateVkLikeJob::class,
            CreateVkProductJob::class,
            EditVkProductJob::class,
            ArchiveVkProductJob::class,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        $this->withHeader('Authorization', 'Bearer '.config('app.api_key'));

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
            'type' => PublicationTaskType::VK_UPLOAD_IMAGES->value,
            'status' => PublicationTaskStatus::QUEUED->value,
        ]);
        $this->assertDatabaseHas('publication_tasks', [
            'publication_id' => $publication->id,
            'type' => PublicationTaskType::VK_POST->value,
            'status' => PublicationTaskStatus::WAITING->value,
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
            ->map(fn (PublicationTaskType $type) => $type->value)
            ->all();

        $this->assertContains(PublicationTaskType::VK_UPLOAD_IMAGES->value, $types);
        $this->assertContains(PublicationTaskType::VK_POST->value, $types);
        $this->assertContains(PublicationTaskType::VK_LOOP_STORY->value, $types);
        $this->assertContains(PublicationTaskType::VK_CREATE_PRODUCT->value, $types);
    }

    public function test_rent_offer_triggers_rent_scenario(): void
    {
        Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload([
            'deal' => 'сдача',
            'price' => 32000,
            'deposit' => 5000,
            'commission' => 16000,
        ]));

        $response->assertOk();
        $offer = Offer::first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::RENT->value, $publication->scenario->value);

        $types = PublicationTask::where('publication_id', $publication->id)
            ->pluck('type')
            ->map(fn (PublicationTaskType $type) => $type->value)
            ->all();

        $this->assertContains(PublicationTaskType::VK_UPLOAD_IMAGES->value, $types);
        $this->assertContains(PublicationTaskType::VK_POST->value, $types);
        $this->assertContains(PublicationTaskType::VK_LOOP_STORY->value, $types);
        $this->assertContains(PublicationTaskType::VK_CREATE_PRODUCT->value, $types);
    }

    public function test_rent_offer_after_announcement_triggers_rent_scenario(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 0,
            'deal' => Deal::RENT_OUT->value,
        ]);
        Publication::factory()->forOffer($prevOffer)->withScenario(ScenarioType::ANNOUNCEMENT)->create();

        $response = $this->postJson('/offer', $this->payload([
            'deal' => 'сдача',
            'price' => 32000,
            'deposit' => 5000,
            'commission' => 16000,
        ]));

        $response->assertOk();
        $offer = Offer::latest('id')->first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::RENT->value, $publication->scenario->value);
    }

    public function test_rent_price_decrease_triggers_price_changed_scenario(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 35000,
            'deal' => Deal::RENT_OUT->value,
            'deposit' => 5000,
            'commission' => 16000,
        ]);
        Publication::factory()->forOffer($prevOffer)->withScenario(ScenarioType::RENT)->create();

        $response = $this->postJson('/offer', $this->payload([
            'deal' => 'сдача',
            'price' => 30000,
            'deposit' => 5000,
            'commission' => 16000,
        ]));

        $response->assertOk();
        $offer = Offer::latest('id')->first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::PRICE_CHANGED->value, $publication->scenario->value);
    }

    public function test_sale_small_price_decrease_does_not_trigger_price_changed_scenario(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
            'deal' => Deal::SALE->value,
        ]);
        Publication::factory()->forOffer($prevOffer)->withScenario(ScenarioType::SALE)->create();

        $response = $this->postJson('/offer', $this->payload([
            'price' => 995_000,
            'deal' => 'продажа',
        ]));

        $response->assertOk();
        $offer = Offer::latest('id')->first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNull($publication);
    }

    public function test_rent_offer_without_deposit_triggers_rent_scenario(): void
    {
        Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload([
            'deal' => 'сдача',
            'price' => 32000,
            'deposit' => null,
            'commission' => 16000,
        ]));

        $response->assertOk();
        $offer = Offer::first();
        $publication = Publication::where('offer_id', $offer->id)->first();

        $this->assertNotNull($offer);
        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::RENT->value, $publication->scenario->value);
        $this->assertNotSame(ScenarioType::SALE->value, $publication->scenario->value);
    }

    public function test_house_alias_and_ust_abakan_city_are_parsed_as_abakan(): void
    {
        Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload([
            'category' => 'жилье на земле',
            'location' => [
                'city' => 'Усть-Абакан',
                'address' => 'ул. Орджоникидзе, 67',
            ],
            'price' => 350000,
        ]));

        $response->assertOk();
        $this->assertDatabaseCount('offers', 1);

        $offer = Offer::first();
        $this->assertNotNull($offer);
        $this->assertSame(Category::HOUSE, $offer->category());
        $this->assertSame(City::ABAKAN, $offer->city());
    }

    public function test_land_category_is_parsed(): void
    {
        Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload([
            'category' => 'участок',
            'price' => 350000,
        ]));

        $response->assertOk();
        $offer = Offer::first();
        $this->assertNotNull($offer);
        $this->assertSame(Category::LAND, $offer->category());
    }

    public function test_unknown_category_saves_offer_without_tasks(): void
    {
        Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload([
            'category' => 'неизвестная-категория',
        ]));

        $response->assertOk();
        $this->assertDatabaseCount('offers', 1);
        $offer = Offer::first();
        $this->assertNotNull($offer);
        $this->assertSame('Неизвестная категория: неизвестная-категория', $offer->skip_reason);
        $this->assertDatabaseCount('publications', 0);
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
            'price' => 1_000_000,
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

        $this->postJson('/offer', $this->payload(['status' => 'архив', 'price' => 1_000_000]));
        $firstArchive = Offer::latest('id')->first();
        $firstPublication = Publication::where('offer_id', $firstArchive->id)->first();
        $this->assertNotNull($firstPublication);
        $this->assertSame(ScenarioType::SOLD->value, $firstPublication->scenario->value);

        // Другая цена, чтобы пройти анти-дубликат вебхука и создать вторую archive-запись.
        $this->postJson('/offer', $this->payload(['status' => 'архив', 'price' => 999_000]));
        $secondArchive = Offer::latest('id')->first();
        $secondPublication = Publication::where('offer_id', $secondArchive->id)->first();
        $this->assertNotNull($secondPublication);
        $this->assertSame(ScenarioType::FEEDBACK->value, $secondPublication->scenario->value);
    }

    public function test_duplicate_offer_is_saved_without_tasks(): void
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
        $this->assertDatabaseCount('offers', 2);
        $duplicate = Offer::query()->latest('id')->first();
        $this->assertNotNull($duplicate);
        $this->assertSame('Дубликат существующего оффера', $duplicate->skip_reason);
        $this->assertDatabaseCount('publications', 0);
    }

    public function test_unknown_agent_saves_offer_without_tasks(): void
    {
        $response = $this->postJson('/offer', $this->payload());

        $response->assertOk();
        $this->assertDatabaseCount('offers', 1);
        $offer = Offer::first();
        $this->assertNotNull($offer);
        $this->assertSame('Не найден агент по номеру телефона', $offer->skip_reason);
        $this->assertNull($offer->agent_id);
        $this->assertDatabaseCount('publications', 0);
    }

    public function test_media_id_copied_from_previous_offer_with_same_url_and_agent(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        $url = 'https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/dfe758a04f96e498c650e184f376ebf0.jpg';
        OfferImage::factory()->for($prevOffer)->withMediaId(123456)->create([
            'original_url' => $url,
            'sort_order' => 0,
        ]);

        $this->postJson('/offer', $this->payload(['price' => 900_000]));

        $newOffer = Offer::latest('id')->first();
        $this->assertNotNull($newOffer);
        $newImage = $newOffer->images->firstWhere('original_url', $url);
        $this->assertNotNull($newImage);
        $this->assertSame(123456, $newImage->media_id);
    }

    public function test_media_id_null_for_new_url(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        OfferImage::factory()->for($prevOffer)->withMediaId(123456)->create([
            'original_url' => 'https://example.com/old-photo.jpg',
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/offer', $this->payload(['price' => 900_000]));

        $this->assertSame(200, $response->status(), 'Response: '.$response->getContent());
        $this->assertDatabaseCount('offers', 2);

        $newOffer = Offer::latest('id')->first();
        $this->assertNotNull($newOffer);
        $this->assertNotSame($prevOffer->id, $newOffer->id);
        $newImage = $newOffer->images->first();
        $this->assertNotNull($newImage);
        $this->assertNull($newImage->media_id);
    }

    public function test_media_id_not_copied_when_agent_changed(): void
    {
        $firstAgent = Agent::factory()->create(['phone' => '79953742476']);
        $secondAgent = Agent::factory()->create(['phone' => '79999999999']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $firstAgent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        $url = 'https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/dfe758a04f96e498c650e184f376ebf0.jpg';
        OfferImage::factory()->for($prevOffer)->withMediaId(123456)->create([
            'original_url' => $url,
            'sort_order' => 0,
        ]);

        $this->postJson('/offer', $this->payload([
            'price' => 900_000,
            'agent' => ['name' => 'Second Agent', 'phone' => '+79999999999'],
        ]));

        $newOffer = Offer::latest('id')->first();
        $this->assertNotNull($newOffer);
        $this->assertSame($secondAgent->id, $newOffer->agent_id);
        $newImage = $newOffer->images->firstWhere('original_url', $url);
        $this->assertNotNull($newImage);
        $this->assertNull($newImage->media_id);
    }

    public function test_no_media_id_query_when_image_urls_empty(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $prevOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '217-100',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 1_000_000,
        ]);
        $url = 'https://kyzyl.brokerplus.ru/images/kyzyl/offer/53226/dfe758a04f96e498c650e184f376ebf0.jpg';
        OfferImage::factory()->for($prevOffer)->withMediaId(123456)->create([
            'original_url' => $url,
            'sort_order' => 0,
        ]);

        DB::enableQueryLog();

        $this->postJson('/offer', $this->payload([
            'price' => 900_000,
            'photos' => [],
        ]));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $newOffer = Offer::latest('id')->first();
        $this->assertNotNull($newOffer);
        $this->assertDatabaseCount('offer_images', 1);

        $whereInQueries = array_filter($queries, fn (array $q): bool => str_contains($q['query'], '`original_url` in'));
        $this->assertEmpty($whereInQueries, 'Query for existing media_id should not be executed when imageUrls is empty');
    }

    public function test_kyzyl_kozhun_alias_is_parsed_as_kyzyl(): void
    {
        $this->assertSame(City::KYZYL, City::tryFromLabel('Кызылский кожуун'));

        Agent::factory()->create(['phone' => '79953742476']);

        $response = $this->postJson('/offer', $this->payload([
            'code' => '317-2147312',
            'location' => [
                'city' => 'Кызылский кожуун',
                'address' => 'ул. Ленина, 1',
            ],
        ]));

        $response->assertOk();
        $offer = Offer::query()->where('code', '317-2147312')->first();
        $this->assertNotNull($offer);
        $this->assertSame(City::KYZYL, $offer->city());
    }

    public function test_rent_out_creates_one_story_and_stops_loop_without_parent_task(): void
    {
        $agent = Agent::factory()->create(['phone' => '79953742476']);
        $previousOffer = Offer::factory()->create([
            'agent_id' => $agent->id,
            'code' => '219-254',
            'status' => OfferStatus::ACTIVE->value,
            'price' => 32000,
            'deal' => Deal::RENT_OUT->value,
        ]);
        Publication::factory()->forOffer($previousOffer)->withScenario(ScenarioType::RENT)->create();
        $loopStory = VkLoopStory::factory()->create([
            'offer_id' => $previousOffer->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/offer', $this->payload([
            'code' => '219-254',
            'deal' => 'сдача',
            'status' => 'архив',
            'price' => 32000,
        ]));

        $response->assertOk();

        $offer = Offer::query()->where('code', '219-254')->latest('id')->first();
        $this->assertNotNull($offer);
        $this->assertNotSame($previousOffer->id, $offer->id);

        $publication = Publication::query()->where('offer_id', $offer->id)->first();
        $this->assertNotNull($publication);
        $this->assertSame(ScenarioType::RENT_OUT, $publication->scenario);

        $this->assertSame(1, PublicationTask::query()
            ->where('publication_id', $publication->id)
            ->where('type', PublicationTaskType::VK_STORY)
            ->count());

        $archiveTask = PublicationTask::query()
            ->where('publication_id', $publication->id)
            ->where('type', PublicationTaskType::VK_ARCHIVE_PRODUCT)
            ->first();
        $this->assertNotNull($archiveTask);
        $this->assertNull($archiveTask->dependent_task_id);

        $endTask = PublicationTask::query()
            ->where('publication_id', $publication->id)
            ->where('type', PublicationTaskType::VK_END_LOOP_STORY)
            ->first();
        $this->assertNotNull($endTask);
        $this->assertNull($endTask->dependent_task_id);
        $this->assertSame(PublicationTaskStatus::QUEUED, $endTask->status);

        $job = new EndVkLoopStoryJob($endTask->id);
        $job->handle(new PublicationTaskDependencyResolver, new TaskDispatcher(new JobResolver));

        $this->assertSame(PublicationTaskStatus::SUCCESS, $endTask->fresh()->status);
        $this->assertFalse((bool) $loopStory->fresh()->is_active);
    }
}
