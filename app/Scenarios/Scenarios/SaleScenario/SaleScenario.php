<?php

namespace App\Scenarios\Scenarios\SaleScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\ActiveStatusRule;
use App\Scenarios\Rules\NewOfferOrAnnouncementRule;
use App\Scenarios\Rules\OfferHasPrice;
use App\Scenarios\Scenario;

class SaleScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::SALE;
    }

    public function rules(): array
    {
        return [
            NewOfferOrAnnouncementRule::class,
            ActiveStatusRule::class,
            OfferHasPrice::class,
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_POST,
            PublicationTaskType::VK_REPOST,
            PublicationTaskType::VK_LOOP_STORY,
            PublicationTaskType::VK_COMMENT,
            PublicationTaskType::VK_CREATE_PRODUCT,
        ];
    }
}
