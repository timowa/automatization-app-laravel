<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cAgent\u003e
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'phone' => '7995' . fake()->numberBetween(1000000, 9999999),
        ];
    }
}
