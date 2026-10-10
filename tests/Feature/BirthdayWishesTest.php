<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Vk\Sex;
use App\Jobs\WishHappyBirthdayJob;
use App\Models\Agent;
use App\Models\AgentVkFriend;
use App\Models\Setting;
use App\Models\VkUser;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BirthdayWishesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_agents_list_shows_gender_birthday_badges(): void
    {
        $agent = Agent::factory()->create();
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => true,
            'birthday_wish_male_text' => 'Текст для мужчин',
            'birthday_wish_female_text' => null,
        ]);

        $response = $this->withSession(['is_admin' => true])->get('/agents');

        $response->assertOk();
        $response->assertSee('Текст поздравления для мужчин', false);
        $response->assertSee('Текст поздравления для женщин', false);
        $response->assertSee('data-gender-badge="male"', false);
        $response->assertSee('data-gender-badge="female"', false);
        $response->assertSee('bg-green-100 text-green-700', false);
        $response->assertSee('bg-yellow-100 text-yellow-800', false);
        $response->assertDontSee('>Вкл<', false);
        $response->assertDontSee('>Выкл<', false);
    }

    public function test_birthday_texts_are_saved_with_setting(): void
    {
        $agent = Agent::factory()->create();

        $response = $this->withSession(['is_admin' => true])->postJson('/agents/settings/'.$agent->id, [
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => true,
            'birthday_wish_male_text' => 'Мужской текст',
            'birthday_wish_female_text' => ' Женский текст ',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => true,
            'has_male_text' => true,
            'has_female_text' => true,
            'birthday_wish_male_text' => 'Мужской текст',
            'birthday_wish_female_text' => 'Женский текст',
        ]);

        $this->assertDatabaseHas('settings', [
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => 1,
            'wish_happy_birthday_female' => 1,
            'birthday_wish_male_text' => 'Мужской текст',
            'birthday_wish_female_text' => 'Женский текст',
        ]);
    }

    public function test_dispatch_command_skips_ineligible_friends(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07'));
        Queue::fake();

        $agent = Agent::factory()->create();
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => true,
            'birthday_wish_male_text' => null,
            'birthday_wish_female_text' => null,
        ]);

        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 101,
            'bdate' => '1990-10-07',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'sex' => Sex::MALE->value,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 102,
            'bdate' => '10-07',
            'first_name' => 'Пётр',
            'last_name' => 'Петров',
            'sex' => Sex::MALE->value,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 103,
            'bdate' => '2015-10-07',
            'first_name' => 'Саша',
            'last_name' => 'Малый',
            'sex' => Sex::MALE->value,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 104,
            'bdate' => '1990-10-07',
            'first_name' => 'Без',
            'last_name' => 'Пола',
            'sex' => Sex::UNSPECIFIED->value,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 105,
            'bdate' => '1990-10-07',
            'first_name' => 'Уже',
            'last_name' => 'Поздравили',
            'sex' => Sex::MALE->value,
            'last_birthday_wish_at' => '2026-10-07',
        ]);

        $this->artisan('vk:dispatch-birthday-wishes')
            ->expectsOutput('Поздравлений запланировано: 1')
            ->assertSuccessful();

        Queue::assertPushed(WishHappyBirthdayJob::class, 1);
        Queue::assertPushed(WishHappyBirthdayJob::class, function (WishHappyBirthdayJob $job): bool {
            return $job->friendUserId === 101;
        });
    }

    public function test_wish_job_sends_custom_text_and_stores_wish_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07'));

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => false,
            'birthday_wish_male_text' => 'Кастомный текст для мужчин',
            'birthday_wish_female_text' => null,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 555,
            'bdate' => '1996-10-07',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'sex' => Sex::MALE->value,
        ]);

        $fake = new FakeVkApiService;
        $this->app->instance(VkApiService::class, $fake);
        Queue::fake();

        (new WishHappyBirthdayJob($agent->id, 555))->handle($fake);

        $this->assertTrue(collect($fake->calls)->contains(
            fn (array $call): bool => $call['method'] === 'sendDirectMessage'
                && $call['user_id'] === 555
                && $call['message'] === 'Кастомный текст для мужчин'
        ));
        Queue::assertNothingPushed();
        $friend = AgentVkFriend::query()
            ->where('agent_id', $agent->id)
            ->where('user_id', 555)
            ->first();
        $this->assertNotNull($friend);
        $this->assertSame('2026-10-07', $friend->last_birthday_wish_at?->toDateString());
    }

    public function test_wish_job_sends_template_when_custom_text_empty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07'));

        $agent = Agent::factory()->create(['name' => 'Тестовый Агент']);
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => false,
            'birthday_wish_male_text' => null,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 557,
            'bdate' => '1996-10-07',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'sex' => Sex::MALE->value,
        ]);

        $fake = new FakeVkApiService;

        (new WishHappyBirthdayJob($agent->id, 557))->handle($fake);

        $message = collect($fake->calls)->firstWhere('method', 'sendDirectMessage')['message'] ?? '';
        $this->assertStringContainsString('Иван', $message);
        $this->assertStringContainsString('Тестовый Агент', $message);
        $this->assertStringContainsString('днём рождения', $message);
    }

    public function test_wish_job_skips_when_already_wished_this_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07'));

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
            'birthday_wish_male_text' => 'Кастомный текст для мужчин',
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 556,
            'bdate' => '1996-10-07',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'sex' => Sex::MALE->value,
            'last_birthday_wish_at' => '2026-03-01',
        ]);

        $fake = new FakeVkApiService;

        (new WishHappyBirthdayJob($agent->id, 556))->handle($fake);

        $this->assertSame([], $fake->calls);
    }
}
