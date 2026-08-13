<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Scenarios\ScenarioRule;

class OfferHasPrice extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        return (int)$offerChanged->current->getPrice() > 0;
    }
}
