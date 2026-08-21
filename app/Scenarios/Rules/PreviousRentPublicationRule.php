<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\ScenarioType;
use App\Models\Publication;
use App\Scenarios\ScenarioRule;

class PreviousRentPublicationRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        if ($offerChanged->previous === null) {
            return false;
        }

        return Publication::whereHas('offer', function ($query) use ($offerChanged): void {
            $query->where('code', $offerChanged->current->code);
        })->where('scenario', ScenarioType::RENT)->exists();
    }
}
