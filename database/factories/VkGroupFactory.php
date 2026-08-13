<?php

namespace Database\Factories;

use App\Models\VkGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cVkGroup\u003e
 */
class VkGroupFactory extends Factory
{
    protected $model = VkGroup::class;

    public function definition(): array
    {
        return [
            'group_id' => fake()->unique()->numberBetween(100000000, 999999999),
            'city' => 2,
        ];
    }
}
