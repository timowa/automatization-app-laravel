<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\Deal;
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

        if ($oldPrice <= $newPrice) {
            return false;
        }

        $deal = $offerChanged->current->deal();

        if ($deal === Deal::RENT_OUT) {
            return true;
        }

        return ($oldPrice - $newPrice) >= 10_000;
    }
}
