<?php

namespace App\Scenarios;

use App\DTO\OfferChanged;

abstract class ScenarioRule
{
    abstract public function passes(OfferChanged $offerChanged): bool;
}
