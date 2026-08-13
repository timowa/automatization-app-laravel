<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Scenarios\ScenarioRule;

class AgentChangedRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        return in_array('agent_id', $offerChanged->changes, true);
    }
}
