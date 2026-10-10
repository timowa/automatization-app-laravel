<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agents_list_shows_birthday_setting_and_offcanvas(): void
    {
        $enabled = Agent::factory()->create(['name' => 'Агент Включен', 'phone' => '79990000001']);
        Setting::create([
            'agent_id' => $enabled->id,
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => true,
            'birthday_wish_male_text' => 'Текст М',
            'birthday_wish_female_text' => 'Текст Ж',
        ]);

        $disabled = Agent::factory()->create(['name' => 'Агент Выключен', 'phone' => '79990000002']);
        Setting::create([
            'agent_id' => $disabled->id,
            'wish_happy_birthday_male' => false,
            'wish_happy_birthday_female' => false,
        ]);

        $response = $this->withSession(['is_admin' => true])->get('/agents');

        $response->assertOk();
        $response->assertSee('Поздравления');
        $response->assertSee('Автоматически поздравлять с днём рождения мужчин', false);
        $response->assertSee('Автоматически поздравлять с днём рождения женщин', false);
        $response->assertSee('id="agents-table"', false);
        $response->assertSee('id="agents-sticky-hscroll"', false);
        $response->assertSee('Столбцы');
        $response->assertSee('Сбросить столбцы');
        $response->assertSee('jquery-3.7.1.min.js', false);
        $response->assertSee('dataTables.min.js', false);
        $response->assertSee('buttons.colVis.min.js', false);
        $response->assertSee('id="agent-settings-offcanvas"', false);
        $response->assertSee('data-agent-id="'.$enabled->id.'"', false);
        $response->assertSee('data-wish-male="1"', false);
        $response->assertSee('data-wish-female="1"', false);
        $response->assertSee('data-wish-male="0"', false);
        $response->assertSee('data-gender-badge="male"', false);
        $response->assertSee('data-gender-badge="female"', false);
        $response->assertSee('bg-green-100 text-green-700', false);
        $response->assertSee('bg-gray-200 text-gray-700', false);
    }

    public function test_birthday_setting_is_saved_without_redirect(): void
    {
        $agent = Agent::factory()->create();

        $response = $this->withSession(['is_admin' => true])->postJson('/agents/settings/'.$agent->id, [
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => false,
            'birthday_wish_male_text' => 'М',
            'birthday_wish_female_text' => 'Ж',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => null,
            'wish_happy_birthday_male' => true,
            'wish_happy_birthday_female' => false,
            'has_male_text' => true,
            'has_female_text' => true,
        ]);

        $this->assertDatabaseHas('settings', [
            'agent_id' => $agent->id,
            'wish_happy_birthday_male' => 1,
            'wish_happy_birthday_female' => 0,
            'birthday_wish_male_text' => 'М',
            'birthday_wish_female_text' => 'Ж',
        ]);
    }

    public function test_agent_form_does_not_contain_birthday_checkbox(): void
    {
        $agent = Agent::factory()->create();

        $response = $this->withSession(['is_admin' => true])->get('/agents/edit/'.$agent->id);

        $response->assertOk();
        $response->assertDontSee('wish_happy_birthday_male', false);
        $response->assertDontSee('wish_happy_birthday_female', false);
    }
}
