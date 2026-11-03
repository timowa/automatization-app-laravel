<?php

declare(strict_types=1);

namespace App\Services\Exclusive;

use App\Models\Offer;
use App\Models\Publication;
use Illuminate\Support\Facades\DB;

final class ExclusiveCooldownRule implements ExclusiveRule
{
    public function rejection(Offer $offer, ?Publication $source, string $trigger): ?string
    {
        if ($trigger !== 'transition') {
            return null;
        }
        $previous = DB::table('exclusive_publication_runs')->where('code', $offer->code)
            ->where('started_at', '>', now()->subDay())->orderByDesc('started_at')->first();

        return $previous === null ? null : 'Уже запускалось за последние 24 часа (запуск №'.$previous->id.')';
    }
}
