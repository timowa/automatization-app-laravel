<?php

namespace Database\Factories;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Models\Offer;
use App\Models\PublicationTask;
use App\Models\VkLoopStory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cVkLoopStory\u003e
 */
class VkLoopStoryFactory extends Factory
{
    protected $model = VkLoopStory::class;

    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'task_id' => PublicationTask::factory()
                ->ofType(PublicationTaskType::VK_LOOP_STORY)
                ->withStatus(PublicationTaskStatus::SUCCESS),
            'is_active' => true,
            'last_published_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
