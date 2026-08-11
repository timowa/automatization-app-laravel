<?php

namespace App\DTO;

use App\Models\Offer;

final readonly class OfferChanged
{
    public function __construct(
        public ?Offer $previous = null,
        public Offer $current,
        public array $changes
    )
    {

    }
}
