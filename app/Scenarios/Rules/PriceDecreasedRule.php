<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Scenarios\ScenarioRule;

class PriceDecreasedRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        if ($offerChanged->previous === null) {
            return false;
        }

        $oldPrice = $offerChanged->previous->getBasePrice();
        $newPrice = $offerChanged->current->getBasePrice();

        return $oldPrice > $newPrice;
    }
}
