<?php

namespace App\DTO;

use App\Enums\Category;
use App\Enums\City;
use App\Enums\Deal;
use App\Enums\OfferStatus;

final readonly class OfferData
{
    public function __construct(
        public int $offerId,
        public string $code,
        public int $stage,
        public OfferStatus $status,
        public ?City $city,
        public int $agentId,
        public int $price,
        public ?int $commission,
        public ?int $deposit,
        public float $area,
        public ?float $kitchenArea,
        public ?float $livingArea,
        public int $rooms,
        public ?int $roomsOffered,
        public ?int $floor,
        public ?int $floors,
        public array $images,
        public ?Deal $deal,
        public ?Category $category,
        public ?array $location,
    )
    {

    }

    public function getArray(): array
    {
        return [
            'offer_id' => $this->offerId,
            'code' => $this->code,
            'stage' => $this->stage,
            'status' => $this->status,
            'city' => $this->city,
            'agent_id' => $this->agentId,
            'price' => $this->price,
            'commission' => $this->commission,
            'deposit' => $this->deposit,
            'area' => $this->area,
            'rooms' => $this->rooms,
            'rooms_offered' => $this->roomsOffered,
            'floor' => $this->floor,
            'floors_total' => $this->floors,
            'images' => $this->images,
            'deal' => $this->deal,
            'category' => $this->category,
            'location' => $this->location,

        ];
    }
}
