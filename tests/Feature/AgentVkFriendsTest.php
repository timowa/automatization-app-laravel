<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Vk\SyncAgentVkFriendsAction;
use App\Enums\Vk\LastSeenPlatform;
use App\Enums\Vk\Relation;
use App\Enums\Vk\Sex;
use App\Models\Agent;
use App\Models\AgentVkFriend;
use App\Models\VkUser;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentVkFriendsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_friends_page_shows_profile_labels_filters_and_last_sync_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-13 02:00:00'));

        $agent = Agent::factory()->create(['name' => 'Маргарита Барби']);
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'friends_synced_at' => Carbon::parse('2026-10-13 02:00:00'),
        ]);
        $this->createFriend($agent->id, [
            'user_id' => 777,
            'first_name' => 'Анна',
            'last_name' => 'Смирнова',
            'nickname' => 'ann',
            'sex' => Sex::FEMALE->value,
            'bdate' => '1990-03-15',
            'relation' => Relation::MARRIED->value,
            'domain' => 'anna_sm',
            'city_name' => 'Кызыл',
            'country_name' => 'Россия',
            'mobile_phone' => '+79990001122',
            'home_phone' => '221100',
            'has_mobile' => true,
            'online' => true,
            'timezone' => 7,
            'last_seen_at' => 1710000000,
            'last_seen_platform' => LastSeenPlatform::ANDROID->value,
            'university_name' => 'ТувГУ',
            'faculty_name' => 'Филологический',
            'graduation' => 2012,
            'status' => 'На связи',
            'photo_id' => '777_1',
            'universities' => [
                ['name' => 'ТувГУ', 'faculty_name' => 'Филологический', 'graduation' => 2012],
            ],
        ]);
        $this->createFriend($agent->id, [
            'user_id' => 888,
            'first_name' => 'Борис',
            'last_name' => 'Орлов',
            'sex' => Sex::MALE->value,
            'city_name' => 'Москва',
            'country_name' => 'Россия',
            'online' => false,
            'relation' => Relation::SINGLE->value,
            'mobile_phone' => '+79991112233',
        ]);
        $this->createFriend($agent->id, [
            'user_id' => 333,
            'first_name' => 'Удаленный',
            'last_name' => 'Профиль',
            'deactivated' => 'deleted',
            'is_closed' => true,
            'home_town' => 'Абакан',
            'occupation_type' => 'work',
            'occupation_name' => 'Магазин',
        ]);

        $other = Agent::factory()->create();
        $this->createFriend($other->id, [
            'user_id' => 999,
            'first_name' => 'Чужой',
            'last_name' => 'Человек',
        ]);

        $response = $this->withSession(['is_admin' => true])->get('/agents/'.$agent->id.'/friends');

        $response->assertOk();
        $response->assertSee('Друзья: Маргарита Барби (3)', false);
        $response->assertSee('Дата последнего обновления 13.10.2026 02:00', false);
        $response->assertSee('Дата рождения', false);
        $response->assertSee('Семейное положение', false);
        $response->assertSee('Мобильный телефон', false);
        $response->assertSee('Часовой пояс', false);
        $response->assertSee('Университеты', false);
        $response->assertSee('name="q"', false);
        $response->assertSee('name="sex"', false);
        $response->assertSee('id="city-filter-input"', false);
        $response->assertSee('Сбросить город', false);
        $response->assertDontSee('id="friends-mode"', false);
        $response->assertDontSee('Чистка', false);
        $response->assertSee('id="friends-scroll-top"', false);
        $response->assertSee('>Вверх<', false);
        $response->assertSee('name="country"', false);
        $response->assertSee('name="relation"', false);
        $response->assertSee('name="page"', false);
        $response->assertSee('name="home_town"', false);
        $response->assertSee('Страница', false);
        $response->assertSee('Родной город', false);
        $response->assertSee('Род занятий', false);
        $response->assertSee('Удалена', false);
        $response->assertSee('Работа: Магазин', false);
        $response->assertDontSee('name="online"', false);
        $response->assertSee('Столбцы', false);
        $response->assertSee('Сбросить столбцы', false);
        $response->assertSee('buttons.colVis.min.js', false);
        $response->assertSee('Анна', false);
        $response->assertSee('15.03.1990', false);
        $response->assertSee('Женский', false);
        $response->assertSee('Женат / замужем', false);
        $response->assertSee('Кызыл', false);
        $response->assertSee('+79990001122', false);
        $response->assertSee('ТувГУ (Филологический, 2012)', false);
        $response->assertSee('UTC+7', false);
        $response->assertSee('Приложение для Android', false);
        $response->assertSee('https://vk.com/anna_sm', false);
        $response->assertDontSee('Чужой', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$agent->id.'/friends?city='.urlencode('Кызыл'))
            ->assertOk()
            ->assertSee('Друзья: Маргарита Барби (1)', false)
            ->assertSee('Анна', false)
            ->assertDontSee('Борис', false)
            ->assertSee('name="city[]"', false)
            ->assertSee('value="Кызыл"', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$agent->id.'/friends?'.http_build_query(['city' => ['Кызыл', 'Москва']]))
            ->assertOk()
            ->assertSee('Друзья: Маргарита Барби (2)', false)
            ->assertSee('Анна', false)
            ->assertSee('Борис', false)
            ->assertDontSee('Удаленный', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$agent->id.'/friends?sex='.Sex::MALE->value)
            ->assertOk()
            ->assertSee('Борис', false)
            ->assertDontSee('Анна', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$agent->id.'/friends?q=79991112233')
            ->assertOk()
            ->assertSee('Борис', false)
            ->assertDontSee('Анна', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$agent->id.'/friends?page=deleted')
            ->assertOk()
            ->assertSee('Удаленный', false)
            ->assertDontSee('Анна', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$agent->id.'/friends?page=active')
            ->assertOk()
            ->assertSee('Анна', false)
            ->assertDontSee('Удаленный', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$agent->id.'/friends?relation='.Relation::SINGLE->value)
            ->assertOk()
            ->assertSee('Борис', false)
            ->assertDontSee('Анна', false);
    }

    public function test_friends_page_requires_admin(): void
    {
        $agent = Agent::factory()->create();

        $this->get('/agents/'.$agent->id.'/friends')->assertRedirect('/login');
    }

    public function test_agents_list_shows_friends_count_and_link(): void
    {
        $agent = Agent::factory()->create(['name' => 'Агент С Друзьями']);
        $this->createFriend($agent->id, ['user_id' => 1, 'first_name' => 'А', 'last_name' => 'А']);
        $this->createFriend($agent->id, ['user_id' => 2, 'first_name' => 'Б', 'last_name' => 'Б']);
        $empty = Agent::factory()->create(['name' => 'Агент Без Друзей']);

        $response = $this->withSession(['is_admin' => true])->get('/agents');

        $response->assertOk();
        $response->assertSee('Количество друзей', false);
        $response->assertSee('href="/agents/'.$agent->id.'/friends"', false);
        $response->assertSee('href="/agents/'.$empty->id.'/friends"', false);
        $response->assertSee('aria-label="Список друзей"', false);
        $response->assertDontSee('<span>/agents/'.$agent->id.'/friends</span>', false);
        $response->assertSee('<span>2</span>', false);
        $response->assertSee('<span>0</span>', false);

        $this->withSession(['is_admin' => true])
            ->get('/agents/'.$empty->id.'/friends')
            ->assertOk()
            ->assertSee('Дата последнего обновления —', false)
            ->assertSee('Друзей не найдено', false);
    }

    public function test_edit_page_links_to_friends(): void
    {
        $agent = Agent::factory()->create();

        $this->withSession(['is_admin' => true])
            ->get('/agents/edit/'.$agent->id)
            ->assertOk()
            ->assertSee('/agents/'.$agent->id.'/friends', false)
            ->assertSee('Друзья', false);
    }

    public function test_sync_saves_extended_fields_and_sync_date_without_wiping_birthday_wish(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-13 02:15:00'));

        $agent = Agent::factory()->create();
        $vkUser = VkUser::factory()->create([
            'agent_id' => $agent->id,
            'vk_user_id' => 500,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 777,
            'first_name' => 'Старое',
            'last_name' => 'Имя',
            'last_birthday_wish_at' => '2026-01-02',
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 100,
            'first_name' => 'Ушедший',
            'last_name' => 'Друг',
        ]);

        $fake = new FakeVkApiService;
        $fake->friendsResponse = [[
            'id' => 777,
            'first_name' => 'Анна',
            'last_name' => 'Смирнова',
            'nickname' => 'ann',
            'bdate' => '15.3.1990',
            'sex' => Sex::FEMALE->value,
            'domain' => 'anna_sm',
            'city' => ['id' => 99, 'title' => 'Кызыл'],
            'country' => ['id' => 1, 'title' => 'Россия'],
            'contacts' => ['mobile_phone' => '+79990001122', 'home_phone' => '221100'],
            'education' => [
                'university' => 10,
                'university_name' => 'ТувГУ',
                'faculty' => 3,
                'faculty_name' => 'Филологический',
                'graduation' => 2012,
            ],
            'has_mobile' => 1,
            'timezone' => 7,
            'last_seen' => ['time' => 1710000000, 'platform' => LastSeenPlatform::ANDROID->value],
            'online' => 1,
            'photo_50' => 'https://example.com/50.jpg',
            'photo_100' => 'https://example.com/100.jpg',
            'photo_200_orig' => 'https://example.com/200.jpg',
            'photo_id' => '777_1',
            'relation' => Relation::MARRIED->value,
            'status' => 'На связи',
            'universities' => [
                ['name' => 'ТувГУ', 'faculty_name' => 'Филологический', 'graduation' => 2012],
            ],
            'can_post' => 1,
            'can_write_private_message' => 1,
        ], [
            'first_name' => 'Без id',
        ]];
        $this->app->instance(VkApiService::class, $fake);

        $action = $this->app->make(SyncAgentVkFriendsAction::class);
        $count = $action->execute($vkUser);

        $this->assertSame(1, $count);
        $friend = AgentVkFriend::query()->where('agent_id', $agent->id)->where('user_id', 777)->first();
        $this->assertNotNull($friend);
        $this->assertSame('Анна', $friend->first_name);
        $this->assertSame('1990-03-15', $friend->bdate);
        $this->assertSame('ann', $friend->nickname);
        $this->assertSame('Кызыл', $friend->city_name);
        $this->assertSame(99, $friend->city_id);
        $this->assertSame('Россия', $friend->country_name);
        $this->assertSame('+79990001122', $friend->mobile_phone);
        $this->assertSame('ТувГУ', $friend->university_name);
        $this->assertSame(2012, $friend->graduation);
        $this->assertTrue($friend->has_mobile);
        $this->assertTrue($friend->online);
        $this->assertSame(7, $friend->timezone);
        $this->assertSame(1710000000, $friend->last_seen_at);
        $this->assertSame(LastSeenPlatform::ANDROID->value, $friend->last_seen_platform);
        $this->assertSame(Relation::MARRIED->value, $friend->relation);
        $this->assertSame('На связи', $friend->status);
        $this->assertSame('777_1', $friend->photo_id);
        $this->assertSame('ТувГУ', $friend->universities[0]['name'] ?? null);
        $this->assertSame('2026-01-02', $friend->last_birthday_wish_at?->toDateString());
        $this->assertTrue($vkUser->fresh()->friends_synced_at?->equalTo(Carbon::parse('2026-10-13 02:15:00')));
        $this->assertSame(1, AgentVkFriend::query()->where('agent_id', $agent->id)->count());
        $this->assertDatabaseMissing('agents_vk_friends', [
            'agent_id' => $agent->id,
            'user_id' => 100,
        ]);

        $fake->friendsResponse = [];
        $this->assertSame(0, $action->execute($vkUser->fresh(), true));
        $this->assertSame(0, AgentVkFriend::query()->where('agent_id', $agent->id)->count());
    }

    public function test_sync_is_skipped_for_a_week_unless_forced(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-13 12:00:00'));

        $agent = Agent::factory()->create();
        $syncedAt = Carbon::parse('2026-10-10 12:00:00');
        $vkUser = VkUser::factory()->create([
            'agent_id' => $agent->id,
            'vk_user_id' => 500,
            'friends_synced_at' => $syncedAt,
        ]);

        $fake = new FakeVkApiService;
        $fake->friendsResponse = [[
            'id' => 42,
            'first_name' => 'Пётр',
            'last_name' => 'Петров',
        ]];
        $this->app->instance(VkApiService::class, $fake);

        $action = $this->app->make(SyncAgentVkFriendsAction::class);

        $this->assertNull($action->execute($vkUser->fresh()));
        $this->assertSame([], $fake->calls);
        $this->assertTrue($vkUser->fresh()->friends_synced_at?->equalTo($syncedAt));
        $this->assertDatabaseMissing('agents_vk_friends', [
            'agent_id' => $agent->id,
            'user_id' => 42,
        ]);

        $this->assertSame(1, $action->execute($vkUser->fresh(), true));
        $this->assertDatabaseHas('agents_vk_friends', [
            'agent_id' => $agent->id,
            'user_id' => 42,
            'first_name' => 'Пётр',
        ]);

        $vkUser->friends_synced_at = Carbon::parse('2026-10-01 12:00:00');
        $vkUser->save();
        $fake->calls = [];
        $fake->friendsResponse = [[
            'id' => 43,
            'first_name' => 'Ольга',
            'last_name' => 'Орлова',
        ]];

        $this->assertSame(1, $action->execute($vkUser->fresh()));
        $this->assertNotSame([], $fake->calls);
    }

    public function test_command_skips_recent_sync_and_schedule_is_weekly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-13 12:00:00'));

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'friends_synced_at' => Carbon::parse('2026-10-12 12:00:00'),
            'is_token_available' => 1,
        ]);

        $fake = new FakeVkApiService;
        $fake->friendsResponse = [[
            'id' => 42,
            'first_name' => 'Пётр',
            'last_name' => 'Петров',
        ]];
        $this->app->instance(VkApiService::class, $fake);

        $this->artisan('vk:sync-friends')
            ->expectsOutputToContain('пропущено: 1')
            ->assertSuccessful();

        $this->assertSame([], $fake->calls);
        $this->assertDatabaseMissing('agents_vk_friends', ['user_id' => 42]);

        $event = collect(app(Schedule::class)->events())
            ->first(fn (object $event): bool => str_contains((string) $event->command, 'vk:sync-friends'));

        $this->assertNotNull($event);
        $this->assertSame('0 2 * * 1', $event->expression);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createFriend(int $agentId, array $attributes): void
    {
        AgentVkFriend::query()->create(array_merge([
            'agent_id' => $agentId,
            'user_id' => 1,
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
        ], $attributes));
    }
}
