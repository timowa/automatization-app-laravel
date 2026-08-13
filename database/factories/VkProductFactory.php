<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Offer;
use App\Models\PublicationTask;
use App\Models\VkProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cVkProduct\u003e
 */
class VkProductFactory extends Factory
{
    protected $model = VkProduct::class;

    public function definition(): array
    {
        $offer = \App\Models\Offer::factory()->create();

        return [
            'offer_id' => $offer->id,
            'agent_id' => $offer->agent_id,
            'group_id' => fake()->unique()->numberBetween(100000000, 999999999),
            'product_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'task_id' => PublicationTask::factory(),
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_archived' => true]);
    }
}
