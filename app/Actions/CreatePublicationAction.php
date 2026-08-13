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
use Illuminate\Support\Facades\Log;

class CreatePublicationAction
{
    public function __construct(
        private PublicationTaskDependenceInspector $dependenceInspector,
        private TaskDispatcher $taskDispatcher,
    )
    {
    }

    public function execute(Offer $offer, Scenario $scenario): void
    {
        $publication = DB::transaction(function () use ($offer, $scenario) {
            $publication = Publication::create([
                'offer_id' => $offer->id,
                'scenario' => $scenario->type()
            ]);

            $tasks = [];
            foreach ($scenario->tasks() as $type) {
                $dependsOn = $this->dependenceInspector->inspect($type);
                if (is_null($dependsOn)) {
                    $create = [
                        'publication_id' => $publication->id,
                        'type' => $type,
                        'status' => PublicationTaskStatus::PENDING
                    ];
                } else {
                    $dependedTask = $tasks[$dependsOn->value];
                    $create = [
                        'publication_id' => $publication->id,
                        'type' => $type,
                        'status' => PublicationTaskStatus::WAITING,
                        'dependent_task_id' => $dependedTask->id
                    ];
                }
                $tasks[$type->value] = PublicationTask::create($create);

            }

            return $publication;

        });

        $this->taskDispatcher->dispatch($publication->id);
    }
}
