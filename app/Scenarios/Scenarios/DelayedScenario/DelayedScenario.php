<?php

namespace App\Scenarios\Scenarios\DelayedScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\DelayedStatusRule;
use App\Scenarios\Scenario;

class DelayedScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::DELAYED;
    }

    public function rules(): array
    {
        return [
            DelayedStatusRule::class,
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
