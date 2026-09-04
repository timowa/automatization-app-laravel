<?php

namespace App\Scenarios\Scenarios\BookedScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\BookedStatusRule;
use App\Scenarios\Rules\PreviousPublicationRule;
use App\Scenarios\Scenario;

class BookedScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::BOOKING;
    }

    public function rules(): array
    {
        return [
            PreviousPublicationRule::class,
            BookedStatusRule::class,
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_UPLOAD_IMAGES,
            PublicationTaskType::VK_POST,
//            PublicationTaskType::VK_LIKE,
            PublicationTaskType::VK_REPOST,
            PublicationTaskType::VK_STORY,
            PublicationTaskType::VK_ARCHIVE_PRODUCT,
            PublicationTaskType::VK_END_LOOP_STORY,
        ];
    }
}
