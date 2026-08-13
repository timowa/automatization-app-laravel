<?php

namespace App\Scenarios\Scenarios\DeletedScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\DeletedStatusRule;
use App\Scenarios\Scenario;

class DeletedScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::DELETED;
    }

    public function rules(): array
    {
        return [
            DeletedStatusRule::class,
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
