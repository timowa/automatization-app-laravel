<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OfferImageUpload;
use App\Models\Publication;
use Illuminate\Support\Collection;

final class PublicationImages
{
    public function images(Publication $publication): Collection
    {
        if ($publication->exclusive_run_id === null) {
            return $publication->offer->images;
        }
        $agent = $publication->publishingAgent();

        return $publication->offer->images->map(function ($image) use ($agent) {
            $upload = OfferImageUpload::firstOrCreate([
                'offer_image_id' => $image->id, 'agent_id' => $agent->id,
            ], ['owner_id' => $agent->vkUser->vk_user_id]);
            $upload->setRelation('image', $image);

            return $upload;
        });
    }

    public function attachments(Publication $publication): array
    {
        $ownerId = $publication->publishingAgent()->vkUser->vk_user_id;

        return $this->images($publication)->whereNotNull('media_id')
            ->map(fn ($image) => 'photo'.($image instanceof OfferImageUpload ? $image->owner_id : $ownerId).'_'.$image->media_id)
            ->take(10)->values()->all();
    }
}
