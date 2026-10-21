<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Vk\FakeVkApiService;
use App\Services\Vk\VkApiService;
use Tests\TestCase;

class NotifyChangelogCommandTest extends TestCase
{
    private FakeVkApiService $vkApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vkApi = new FakeVkApiService;
        $this->app->instance(VkApiService::class, $this->vkApi);
    }

    public function test_command_sends_latest_release_message(): void
    {
        config([
            'changelog.current' => '9.9',
            'changelog.releases' => [
                [
                    'version' => '9.9',
                    'date' => '2026-10-21',
                    'changes' => [
                        'Первое изменение.',
                        'Второе изменение.',
                    ],
                ],
                [
                    'version' => '9.8',
                    'date' => '2026-10-01',
                    'changes' => ['Старое изменение.'],
                ],
            ],
        ]);

        $this->artisan('vk:notify-changelog')
            ->assertSuccessful();

        $this->assertCount(1, $this->vkApi->calls);
        $this->assertSame('sendTechMessage', $this->vkApi->calls[0]['method']);

        $message = $this->vkApi->calls[0]['message'];
        $this->assertStringContainsString('Обновление v9.9 (2026-10-21)', $message);
        $this->assertStringContainsString('• Первое изменение.', $message);
        $this->assertStringContainsString('• Второе изменение.', $message);
        $this->assertStringNotContainsString('Старое изменение.', $message);
    }

    public function test_command_skips_when_no_releases(): void
    {
        config([
            'changelog.current' => '1.0',
            'changelog.releases' => [],
        ]);

        $this->artisan('vk:notify-changelog')
            ->expectsOutput('В changelog нет релизов')
            ->assertSuccessful();

        $this->assertSame([], $this->vkApi->calls);
    }

    public function test_command_succeeds_when_send_fails(): void
    {
        config([
            'changelog.current' => '1.0',
            'changelog.releases' => [
                [
                    'version' => '1.0',
                    'date' => '2026-01-01',
                    'changes' => ['Старт.'],
                ],
            ],
        ]);

        $this->vkApi->setFailNext('Exception', 'VK notify credentials not configured');

        $this->artisan('vk:notify-changelog')
            ->assertSuccessful();

        $this->assertSame('sendTechMessage', $this->vkApi->calls[0]['method']);
    }
}
