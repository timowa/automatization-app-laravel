<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost;

use App\Enums\Category;
use App\Enums\Deal;

class VkPostContext
{
    public function __construct(
        public string $address,
        public int $rooms,
        public float $area,
        public ?float $kitchenArea,
        public ?int $floor,
        public ?int $floorsTotal,
        public int $price,
        public ?int $commission,
        public ?int $deposit,
        public string $agentName,
        public string $agentPhone,
        public string $cityName,
        public array $images,
        public int $offerId,
        public Deal $deal,
        public Category $category,
    ) {
    }

    public function getPrice(): string
    {
        return formatPrice($this->price);
    }

    public function getCommission(): ?string
    {
        return $this->commission !== null ? formatPrice($this->commission) : null;
    }

    public function getDeposit(): ?string
    {
        return $this->deposit !== null ? formatPrice($this->deposit) : null;
    }

    public function getArea(): ?string
    {
        return $this->area > 0 ? formatArea($this->area) : null;
    }

    public function getKitchenArea(): ?string
    {
        return $this->kitchenArea !== null && $this->kitchenArea > 0 ? formatArea($this->kitchenArea) : null;
    }

    public function getRooms(): ?string
    {
        return $this->rooms > 0 ? (string) $this->rooms : null;
    }

    public function getFloor(): ?int
    {
        return $this->floor !== null && $this->floor > 0 ? $this->floor : null;
    }

    public function getFloorsTotal(): ?int
    {
        return $this->floorsTotal !== null && $this->floorsTotal > 0 ? $this->floorsTotal : null;
    }

    public function getFloorLine(): ?string
    {
        $floor = $this->getFloor();
        $total = $this->getFloorsTotal();

        if ($floor !== null && $total !== null) {
            return "Этаж: {$floor} / {$total}";
        }

        if ($floor !== null) {
            return "Этаж: {$floor}";
        }

        if ($total !== null) {
            return "Этажей в доме: {$total}";
        }

        return null;
    }

    public function getDeal(): string
    {
        return $this->deal->label();
    }

    public function getCategory(): string
    {
        return $this->category->label();
    }
}
