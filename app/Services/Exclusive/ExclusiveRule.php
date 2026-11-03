<?php

declare(strict_types=1);

namespace App\Services\Exclusive;

use App\Models\Offer;
use App\Models\Publication;

interface ExclusiveRule
{
    public function rejection(Offer $offer, ?Publication $source, string $trigger): ?string;
}
