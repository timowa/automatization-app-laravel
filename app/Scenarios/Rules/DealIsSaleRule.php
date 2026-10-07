<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\Deal;
use App\Scenarios\ScenarioRule;

class DealIsSaleRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        return $offerChanged->current->deal() === Deal::SALE;
    }
}
