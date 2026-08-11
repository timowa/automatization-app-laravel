<?php

declare(strict_types=1);

namespace App\Events;


class OfferCreatedEvent
{
    public function __construct(
        public ?int $prevOfferId = null,
        public int $newOfferId
    )
    {
    }
}
