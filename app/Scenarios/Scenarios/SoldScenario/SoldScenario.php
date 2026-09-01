<?php

namespace App\Scenarios\Scenarios\SoldScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\ArchiveStatusRule;
use App\Scenarios\Rules\FirstArchiveRecordRule;
use App\Scenarios\Rules\PreviousSalePublicationRule;
use App\Scenarios\Scenario;

class SoldScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::SOLD;
    }

    public function rules(): array
    {
        return [
            PreviousSalePublicationRule::class,
            ArchiveStatusRule::class,
            FirstArchiveRecordRule::class,
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_UPLOAD_IMAGES,
            PublicationTaskType::VK_POST,
            PublicationTaskType::VK_LIKE,
            PublicationTaskType::VK_REPOST,
            PublicationTaskType::VK_STORY,
            PublicationTaskType::VK_ARCHIVE_PRODUCT,
            PublicationTaskType::VK_END_LOOP_STORY,
        ];
    }
}
