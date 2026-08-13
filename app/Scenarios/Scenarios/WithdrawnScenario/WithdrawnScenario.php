<?php

namespace App\Scenarios\Scenarios\WithdrawnScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\RemovedStatusRule;
use App\Scenarios\Scenario;

class WithdrawnScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::WITHDRAWN;
    }

    public function rules(): array
    {
        return [
            RemovedStatusRule::class,
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_END_LOOP_STORY,
            PublicationTaskType::VK_ARCHIVE_PRODUCT,
        ];
    }
}
