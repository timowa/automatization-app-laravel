<?php

namespace App\Scenarios\Scenarios\AnnouncementScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\NewOfferRule;
use App\Scenarios\Rules\OfferHasNotPrice;
use App\Scenarios\Scenario;

class AnnouncementScenario extends Scenario
{

    public function type(): ScenarioType
    {
        return ScenarioType::ANNOUNCEMENT;
    }

    public function rules(): array
    {
        return [
            NewOfferRule::class,
            OfferHasNotPrice::class
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_UPLOAD_IMAGES,
            PublicationTaskType::VK_POST,
//            PublicationTaskType::VK_LIKE,
            PublicationTaskType::VK_STORY,
            PublicationTaskType::VK_REPOST
        ];
    }
}
