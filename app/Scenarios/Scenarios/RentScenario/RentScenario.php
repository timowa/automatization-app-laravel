<?php

namespace App\Scenarios\Scenarios\RentScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\ActiveStatusRule;
use App\Scenarios\Rules\DealIsRentOutRule;
use App\Scenarios\Rules\HasDepositAndCommissionRule;
use App\Scenarios\Rules\NewOfferOrAnnouncementRule;
use App\Scenarios\Rules\OfferHasPrice;
use App\Scenarios\Scenario;

class RentScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::RENT;
    }

    public function rules(): array
    {
        return [
            NewOfferOrAnnouncementRule::class,
            ActiveStatusRule::class,
            OfferHasPrice::class,
            DealIsRentOutRule::class,
            HasDepositAndCommissionRule::class,
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_UPLOAD_IMAGES,
            PublicationTaskType::VK_POST,
            PublicationTaskType::VK_LIKE,
            PublicationTaskType::VK_REPOST,
            PublicationTaskType::VK_LOOP_STORY,
            PublicationTaskType::VK_COMMENT,
            PublicationTaskType::VK_CREATE_PRODUCT,
        ];
    }
}
