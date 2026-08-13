<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\OfferStatus;
use App\Scenarios\ScenarioRule;

class NewOfferRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        if ($offerChanged->previous !== null) {
            return false;
        }

        return $offerChanged->current->status() === OfferStatus::ACTIVE;
    }
}
