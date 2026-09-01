<?php

declare(strict_types=1);

namespace App\Services\Vk\Stories;

use App\Models\VkWallPost;

class VkStoriesContextFactory
{
    public function getContext(int $postId): VkStoriesContext
    {
        $post = VkWallPost::findOrFail($postId);
        $offer = $post->offer;
        $agent = $offer->agent;

        return new VkStoriesContext(
            agentName: $agent->name,
            price: $offer->getPrice(),
            commission: $offer->commission,
            deposit: $offer->deposit,
            address: $offer->getAddressFromLocation(),
            rooms: (int) $offer->rooms,
            image: $offer->images->first()?->original_url ?? '',
            deal: $offer->deal() ?? \App\Enums\Deal::SALE,
            category: $offer->category() ?? \App\Enums\Category::APARTMENT,
            postId: $post->getFullId(),
            offerId: (int) $post->offer_id,
        );
    }
}
