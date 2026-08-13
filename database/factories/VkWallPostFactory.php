<?php

namespace Database\Factories;

use App\Models\VkWallPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cVkWallPost\u003e
 */
class VkWallPostFactory extends Factory
{
    protected $model = VkWallPost::class;

    public function definition(): array
    {
        return [
            'offer_id' => OfferFactory::new(),
            'post_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'owner_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'task_id' => PublicationTaskFactory::new(),
        ];
    }

    public function forOffer(int $offerId): static
    {
        return $this->state(fn () => ['offer_id' => $offerId]);
    }

    public function forTask(int $taskId): static
    {
        return $this->state(fn () => ['task_id' => $taskId]);
    }
}
