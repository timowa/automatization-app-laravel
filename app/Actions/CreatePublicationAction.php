<?php

namespace App\Actions;

use App\Enums\PublicationTaskStatus;
use App\Helpers\PublicationTaskDependenceInspector;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Scenarios\Scenario;
use App\Scenarios\TaskDispatcher;
use Illuminate\Support\Facades\DB;

class CreatePublicationAction
{
    public function __construct(
        private PublicationTaskDependenceInspector $dependenceInspector,
        private TaskDispatcher $taskDispatcher,
    ) {}

    public function execute(Offer $offer, Scenario $scenario): void
    {
        $publication = DB::transaction(function () use ($offer, $scenario) {
            $publication = Publication::create([
                'offer_id' => $offer->id,
                'scenario' => $scenario->type(),
            ]);

            $tasks = [];
            foreach ($scenario->tasks() as $type) {
                $dependsOn = $this->dependenceInspector->inspect($type);
                $parentTask = $dependsOn !== null ? ($tasks[$dependsOn->value] ?? null) : null;

                $attributes = [
                    'publication_id' => $publication->id,
                    'type' => $type,
                    'status' => $parentTask !== null
                        ? PublicationTaskStatus::WAITING
                        : PublicationTaskStatus::PENDING,
                ];

                if ($parentTask !== null) {
                    $attributes['dependent_task_id'] = $parentTask->id;
                }

                $tasks[$type->value] = PublicationTask::create($attributes);
            }

            return $publication;

        });

        $this->taskDispatcher->dispatch($publication->id);
    }
}
