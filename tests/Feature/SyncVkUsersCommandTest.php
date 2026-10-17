<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Vk\SyncVkUserAction;
use App\Models\VkUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncVkUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_does_not_poll_users_with_unavailable_token(): void
    {
        $available = VkUser::factory()->create([
            'is_token_available' => true,
            'vk_token' => 'live-token',
        ]);
        VkUser::factory()->create([
            'is_token_available' => false,
            'vk_token' => 'revoked-token',
        ]);
        VkUser::factory()->create([
            'is_token_available' => true,
            'vk_token' => '',
        ]);

        $action = $this->createMock(SyncVkUserAction::class);
        $action->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (VkUser $user) use ($available): bool {
                return $user->id === $available->id;
            }))
            ->willReturn(false);

        $this->app->instance(SyncVkUserAction::class, $action);

        $this->artisan('vk:sync-users')->assertSuccessful();
    }
}
