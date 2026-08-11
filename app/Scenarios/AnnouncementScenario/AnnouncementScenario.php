<?php

namespace App\Scenarios\AnnouncementScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\AnnouncementScenario\Rules\NewOfferRule;
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
            NewOfferRule::class
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_POST,
            PublicationTaskType::VK_STORY,
            PublicationTaskType::VK_REPOST
        ];
    }
}
