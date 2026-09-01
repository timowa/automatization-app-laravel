<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\OfferImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cOffer\u003e
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        return [
            'offer_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'code' => (string) fake()->unique()->numberBetween(100, 999).'-'.fake()->numberBetween(1, 999),
            'stage' => 3,
            'status' => 1,
            'city' => 2,
            'location' => ['city' => 'Кызыл', 'address' => 'ул. Калинина, 24'],
            'agent_id' => AgentFactory::new(),
            'price' => 1_500_000,
            'area' => 51.6,
            'kitchen_area' => 10,
            'living_area' => 35,
            'rooms' => 2,
            'rooms_offered' => null,
            'floor' => 3,
            'floors_total' => 5,
            'commission' => null,
            'deposit' => null,
            'deal' => 1,
            'category' => 1,
        ];
    }

    public function withoutPrice(): static
    {
        return $this->state(fn () => ['price' => 0]);
    }

    public function withStatus(int $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn () => ['code' => $code]);
    }

    public function withAgent(int $agentId): static
    {
        return $this->state(fn () => ['agent_id' => $agentId]);
    }

    public function withDeal(int $deal): static
    {
        return $this->state(fn () => ['deal' => $deal]);
    }

    public function withPrice(int $price): static
    {
        return $this->state(fn () => ['price' => $price]);
    }

    public function withImages(int $count = 1): static
    {
        return $this->afterCreating(function (Offer $offer) use ($count): void {
            OfferImage::factory()
                ->count($count)
                ->sequence(fn (int $sequence) => ['sort_order' => $sequence])
                ->create(['offer_id' => $offer->id]);
        });
    }
}
