<?php

namespace App\Scenarios\Rules;

use App\DTO\OfferChanged;
use App\Models\Offer;
use App\Models\Publication;
use App\Scenarios\ScenarioRule;
use Illuminate\Support\Facades\DB;

class IsNotDuplicateRule extends ScenarioRule
{

    public function passes(OfferChanged $offerChanged): bool
    {
        $offers = Offer::where('code', $offerChanged->current->code)->all();
        $current = $offerChanged->current;
        foreach ($offers as $offer) {
            if ($offer->stage === $current->stage
            && $offer->status === $current->status
            && $offer->price === $current->price
            && $offer->agent_id === $current->agent_id) {
                $publication = $offer->publication();
                if (!is_null($publication)) {
                    return false;
                }
            }
        }
        return true;
    }
}
