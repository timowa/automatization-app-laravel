<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Scenarios\ScenarioRule;

class HasDepositAndCommissionRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        return $offerChanged->current->deposit !== null
            && $offerChanged->current->commission !== null;
    }
}
