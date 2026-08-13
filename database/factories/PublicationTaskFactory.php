<?php

namespace Database\Factories;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Models\Publication;
use App\Models\PublicationTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cPublicationTask\u003e
 */
class PublicationTaskFactory extends Factory
{
    protected $model = PublicationTask::class;

    public function definition(): array
    {
        return [
            'publication_id' => Publication::factory(),
            'type' => PublicationTaskType::VK_POST,
            'status' => PublicationTaskStatus::PENDING,
            'error' => null,
            'external_id' => null,
            'dependent_task_id' => null,
        ];
    }

    public function ofType(PublicationTaskType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function withStatus(PublicationTaskStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function dependsOn(PublicationTask $task): static
    {
        return $this->state(fn () => [
            'dependent_task_id' => $task->id,
            'status' => PublicationTaskStatus::WAITING,
        ]);
    }
}
