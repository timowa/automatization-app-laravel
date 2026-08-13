<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Scenarios\ScenarioRule;

class FirstArchiveRecordRule extends ScenarioRule
{
    public function passes(OfferChanged $offerChanged): bool
    {
        $count = Offer::where('code', $offerChanged->current->code)
            ->where('status', OfferStatus::ARCHIVE->value)
            ->where('id', '<', $offerChanged->current->id)
            ->count();

        return $count === 0;
    }
}
