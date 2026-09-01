<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Offer;
use App\Models\OfferImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferImage>
 */
class OfferImageFactory extends Factory
{
    protected $model = OfferImage::class;

    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'original_url' => fake()->unique()->imageUrl(),
            'media_id' => null,
            'sort_order' => 0,
        ];
    }

    public function withMediaId(?int $mediaId = null): static
    {
        return $this->state(fn () => [
            'media_id' => $mediaId ?? fake()->numberBetween(1, 1_000_000),
        ]);
    }

    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn () => ['sort_order' => $sortOrder]);
    }
}