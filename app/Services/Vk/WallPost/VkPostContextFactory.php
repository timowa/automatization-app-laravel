<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost;

use App\Enums\Category;
use App\Enums\Deal;
use App\Models\Agent;
use App\Models\Offer;

class VkPostContextFactory
{
    public function getContext(int $offerId, ?int $agentId = null): VkPostContext
    {
        $offer = Offer::findOrFail($offerId);
        $agent = $agentId !== null ? Agent::findOrFail($agentId) : $offer->agent;
        $city = $offer->city();

        $prevOffer = Offer::where('code', $offer->code)
            ->where('id', '<', $offer->id)
            ->latest('id')
            ->first();

        return new VkPostContext(
            address: $offer->getAddressFromLocation(),
            rooms: (int) $offer->rooms,
            area: (float) $offer->area,
            livingArea: $offer->living_area,
            kitchenArea: $offer->kitchen_area,
            floor: $offer->floor,
            floorsTotal: $offer->floors_total,
            price: $offer->getPrice(),
            oldPrice: $prevOffer?->getPrice(),
            commission: $offer->commission,
            deposit: $offer->deposit,
            agentName: $agent->name,
            agentPhone: $agent->phone,
            cityName: $city?->label() ?? '',
            offerId: (int) $offer->offer_id,
            deal: $offer->deal() ?? Deal::SALE,
            category: $offer->category() ?? Category::APARTMENT,
            code: $offer->code,
        );
    }
}
