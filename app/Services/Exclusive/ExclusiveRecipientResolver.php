<?php

declare(strict_types=1);

namespace App\Services\Exclusive;

use App\Models\Offer;
use Illuminate\Support\Facades\DB;

final class ExclusiveRecipientResolver
{
    /** @return list<int> */
    public function resolve(Offer $offer): array
    {
        return DB::table('offers')->join('agents', 'agents.id', '=', 'offers.agent_id')
            ->where('offers.city', $offer->city)->where('agents.id', '!=', $offer->agent_id)
            ->distinct()->orderBy('agents.id')->pluck('agents.id')->map(fn ($id) => (int) $id)->all();
    }
}
