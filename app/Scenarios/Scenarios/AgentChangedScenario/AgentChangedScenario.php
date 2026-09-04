<?php

namespace App\Scenarios\Scenarios\AgentChangedScenario;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Scenarios\Rules\ActiveStatusRule;
use App\Scenarios\Rules\AgentChangedRule;
use App\Scenarios\Rules\OfferHasPrice;
use App\Scenarios\Rules\PreviousPublicationRule;
use App\Scenarios\Scenario;

class AgentChangedScenario extends Scenario
{
    public function type(): ScenarioType
    {
        return ScenarioType::AGENT_CHANGED;
    }

    public function rules(): array
    {
        return [
            PreviousPublicationRule::class,
            AgentChangedRule::class,
            ActiveStatusRule::class,
            OfferHasPrice::class,
        ];
    }

    public function tasks(): array
    {
        return [
            PublicationTaskType::VK_UPLOAD_IMAGES,
            PublicationTaskType::VK_POST,
//            PublicationTaskType::VK_LIKE,
            PublicationTaskType::VK_REPOST,
            PublicationTaskType::VK_LOOP_STORY,
            PublicationTaskType::VK_COMMENT,
            PublicationTaskType::VK_EDIT_PRODUCT,
        ];
    }
}
