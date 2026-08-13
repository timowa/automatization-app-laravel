<?php

namespace Database\Factories;

use App\Enums\ScenarioType;
use App\Models\Offer;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cPublication\u003e
 */
class PublicationFactory extends Factory
{
    protected $model = Publication::class;

    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'scenario' => ScenarioType::SALE,
        ];
    }

    public function forOffer(Offer $offer): static
    {
        return $this->state(fn () => ['offer_id' => $offer->id]);
    }

    public function withScenario(ScenarioType $scenario): static
    {
        return $this->state(fn () => ['scenario' => $scenario]);
    }
}
