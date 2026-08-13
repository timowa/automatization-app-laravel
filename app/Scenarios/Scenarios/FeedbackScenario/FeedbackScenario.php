<?php

namespace App\Scenarios\Scenarios\FeedbackScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\ArchiveStatusRule;
use App\Scenarios\Rules\PreviousPublicationRule;
use App\Scenarios\Scenario;

class FeedbackScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::FEEDBACK;
    }

    public function rules(): array
    {
        return [
            PreviousPublicationRule::class,
            ArchiveStatusRule::class,
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_POST,
            PublicationTaskType::VK_REPOST,
            PublicationTaskType::VK_STORY,
            PublicationTaskType::VK_ARCHIVE_PRODUCT,
            PublicationTaskType::VK_END_LOOP_STORY,
        ];
    }
}
