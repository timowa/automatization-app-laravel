<?php

namespace App\Scenarios\Scenarios\RentOutScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\ArchiveStatusRule;
use App\Scenarios\Rules\FirstArchiveRecordRule;
use App\Scenarios\Rules\PreviousRentPublicationRule;
use App\Scenarios\Scenario;

class RentOutScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::RENT_OUT;
    }

    public function rules(): array
    {
        return [
            PreviousRentPublicationRule::class,
            ArchiveStatusRule::class,
            FirstArchiveRecordRule::class,
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
