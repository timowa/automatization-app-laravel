<?php

namespace App\Scenarios\AnnouncementScenario\Rules;

use App\DTO\OfferChanged;
use App\Scenarios\ScenarioRule;

class NewOfferRule extends ScenarioRule
{

    public function passes(OfferChanged $offerChanged): bool
    {
        return is_null($offerChanged->previous);
    }
}
