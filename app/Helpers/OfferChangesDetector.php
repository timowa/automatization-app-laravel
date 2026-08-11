<?php

namespace App\Helpers;

use App\DTO\OfferChanged;
use App\Models\Offer;

final class OfferChangesDetector
{
    private const TRACKED_ATTRIBUTES = [
        'price',
        'status',
        'stage',
        'agent_id',
    ];
    public function detect(?Offer $previous, Offer $current): array
    {
        if ($previous === null) {
            return self::TRACKED_ATTRIBUTES;
        }

        return array_values(array_filter(
            self::TRACKED_ATTRIBUTES,
            fn (string $attribute) =>
                $previous->getAttribute($attribute)
                !== $current->getAttribute($attribute),
        ));
    }
}
