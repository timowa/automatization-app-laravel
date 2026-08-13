<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\ScenarioType;
use App\Models\Publication;
use App\Scenarios\ScenarioRule;

class NewOfferOrAnnouncementRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        if ($offerChanged->previous === null) {
            return true;
        }

        $prevPublication = Publication::where('offer_id', $offerChanged->previous->id)
            ->orderByDesc('id')
            ->first();

        return $prevPublication !== null && $prevPublication->scenario === ScenarioType::ANNOUNCEMENT;
    }
}
