<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StandardTextKey;
use App\Enums\Vk\Sex;
use App\Jobs\BirthdayDiscountJob;
use App\Jobs\ProcessFriendRequestWelcomeJob;
use App\Jobs\WishHappyBirthdayJob;
use App\Models\Agent;
use App\Models\AgentVkFriend;
use App\Models\AgentVkFriendRequest;
use App\Models\Setting;
use App\Models\StandardComment;
use App\Models\StandardText;
use App\Models\VkUser;
use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\Message\BirthdayDiscountTextGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiscStandardTextsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_migration_seeds_standard_texts_and_comments(): void
    {
        $this->assertDatabaseHas('standard_texts', [
            'key' => StandardTextKey::BirthdayWishMale->value,
        ]);
        $this->assertDatabaseHas('standard_texts', [
            'key' => StandardTextKey::FriendRequestWelcome->value,
        ]);
        $this->assertGreaterThanOrEqual(30, StandardComment::query()->count());
    }

    public function test_misc_page_requires_admin_and_shows_tabs(): void
    {
        $this->withoutVite();

        $this->get('/misc')->assertRedirect();

        $response = $this->withSession(['is_admin' => true])->get('/misc');
        $response->assertOk();
        $response->assertSee('Поздравления с ДР', false);
        $response->assertSee('Скидка на ДР', false);
        $response->assertSee('Автоприветствие', false);
        $response->assertSee('Комментарии', false);
        $response->assertSee('Имя агента', false);
        $response->assertSee('Прочее', false);
    }

    public function test_can_update_standard_text_via_ajax(): void
    {
        $response = $this->withSession(['is_admin' => true])->putJson(
            '/misc/texts/'.StandardTextKey::BirthdayWishMale->value,
            ['body' => 'Новый текст {{friend_name}}']
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'body' => 'Новый текст {{friend_name}}',
        ]);

        $this->assertDatabaseHas('standard_texts', [
            'key' => StandardTextKey::BirthdayWishMale->value,
            'body' => 'Новый текст {{friend_name}}',
        ]);
    }

    public function test_comment_crud_via_ajax(): void
    {
        $create = $this->withSession(['is_admin' => true])->postJson('/misc/comments', [
            'body' => 'Тестовый комментарий {{agent_name}}',
        ]);
        $create->assertOk();
        $id = $create->json('comment.id');
        $this->assertNotNull($id);

        $update = $this->withSession(['is_admin' => true])->putJson('/misc/comments/'.$id, [
            'body' => 'Обновлённый комментарий',
        ]);
        $update->assertOk();
        $this->assertDatabaseHas('standard_comments', [
            'id' => $id,
            'body' => 'Обновлённый комментарий',
        ]);

        $delete = $this->withSession(['is_admin' => true])->deleteJson('/misc/comments/'.$id);
        $delete->assertOk();
        $this->assertDatabaseMissing('standard_comments', ['id' => $id]);
    }

    public function test_wish_job_stops_when_standard_text_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07'));

        StandardText::query()
            ->where('key', StandardTextKey::BirthdayWishMale)
            ->update(['body' => '']);

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
            'first_name' => 'Агент',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
            'birthday_wish_male_text' => null,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 901,
            'bdate' => '1996-10-07',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'sex' => Sex::MALE->value,
        ]);

        $fake = new FakeVkApiService;
        (new WishHappyBirthdayJob($agent->id, 901))->handle($fake);

        $this->assertSame([], $fake->calls);
    }

    public function test_discount_job_stops_when_text_missing(): void
    {
        StandardText::query()
            ->where('key', StandardTextKey::BirthdayDiscount)
            ->update(['body' => null]);

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 902,
            'bdate' => '1990-01-01',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'sex' => Sex::MALE->value,
        ]);

        $fake = new FakeVkApiService;
        (new BirthdayDiscountJob($agent->id, 902))->handle($fake, app(BirthdayDiscountTextGenerator::class));

        $this->assertSame([], $fake->calls);
    }

    public function test_welcome_job_stops_when_text_missing(): void
    {
        StandardText::query()
            ->where('key', StandardTextKey::FriendRequestWelcome)
            ->delete();

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
            'first_name' => 'Мария',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'friend_request_welcome_enabled' => true,
            'friend_request_welcome_text' => null,
        ]);
        $request = AgentVkFriendRequest::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 333,
            'first_name' => 'Гость',
            'is_closed' => false,
            'payload' => ['user_id' => 333],
        ]);

        $fake = new FakeVkApiService;
        (new ProcessFriendRequestWelcomeJob($request->id))->handle($fake);

        $this->assertFalse(collect($fake->calls)->contains(
            fn (array $call): bool => $call['method'] === 'sendDirectMessage'
        ));
    }

    public function test_wish_job_renders_placeholders_from_vk_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07'));

        StandardText::query()->where('key', StandardTextKey::BirthdayWishMale)->update([
            'body' => 'Привет, {{friend_name}} {{friend_lastname}}! От {{agent_name}} {{agent_lastname}}, {{brokerplus}}',
        ]);

        $agent = Agent::factory()->create();
        VkUser::factory()->create([
            'agent_id' => $agent->id,
            'is_token_available' => true,
            'vk_token' => 'token',
            'first_name' => 'Ольга',
            'last_name' => 'Смирнова',
        ]);
        Setting::create([
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => true,
        ]);
        AgentVkFriend::query()->create([
            'agent_id' => $agent->id,
            'user_id' => 910,
            'bdate' => '1996-10-07',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'sex' => Sex::MALE->value,
        ]);

        $fake = new FakeVkApiService;
        (new WishHappyBirthdayJob($agent->id, 910))->handle($fake);

        $message = collect($fake->calls)->firstWhere('method', 'sendDirectMessage')['message'] ?? '';
        $this->assertSame('Привет, Иван Иванов! От Ольга Смирнова, БрокерПлюс', $message);
    }
}
