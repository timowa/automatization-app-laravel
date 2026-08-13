<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\OfferStatus;
use App\Scenarios\ScenarioRule;

abstract class StatusRule extends ScenarioRule
{
    protected OfferStatus $expectedStatus;

    public function __construct(OfferStatus $expectedStatus)
    {
        $this->expectedStatus = $expectedStatus;
    }

    public function passes(OfferChanged $offerChanged): bool
    {
        return $offerChanged->current->status() === $this->expectedStatus;
    }
}
