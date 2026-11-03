<?php

declare(strict_types=1);

namespace App\Scenarios\Scenarios\ExclusiveScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Scenario;

final class ExclusiveScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::EXCLUSIVE;
    }

    public function rules(): array
    {
        return [];
    }

    public function tasks(): array
    {
        return [PublicationTaskType::VK_UPLOAD_IMAGES, PublicationTaskType::VK_POST, PublicationTaskType::VK_STORY];
    }
}
