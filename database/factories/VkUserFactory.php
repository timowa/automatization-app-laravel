<?php

namespace Database\Factories;

use App\Models\VkUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cVkUser\u003e
 */
class VkUserFactory extends Factory
{
    protected $model = VkUser::class;

    public function definition(): array
    {
        return [
            'agent_id' => AgentFactory::new(),
            'vk_user_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'vk_token' => 'test-token',
            'email' => fake()->email,
            'first_name' => fake()->firstName,
            'last_name' => fake()->lastName,
            'screen_name' => fake()->userName,
            'is_token_available' => true,
            'is_token_valid' => true,
        ];
    }

    public function withoutToken(): static
    {
        return $this->state(fn () => ['vk_token' => '']);
    }

    public function withInvalidToken(): static
    {
        return $this->state(fn () => ['is_token_valid' => false]);
    }
}
