<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Enums\OfferStatus;
use App\Scenarios\Rules\StatusRule;

class DeletedStatusRule extends StatusRule
{
    public function __construct()
    {
        parent::__construct(OfferStatus::DELETED);
    }
}
