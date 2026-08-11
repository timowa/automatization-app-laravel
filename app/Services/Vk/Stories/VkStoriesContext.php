<?php

declare(strict_types=1);

namespace App\Services\Vk\Stories;

use App\Enums\Category;
use App\Enums\Deal;

class VkStoriesContext
{
    public function __construct(
        public string $agentName,
        public int $price,
        public ?int $commission,
        public ?int $deposit,
        public string $address,
        public int $rooms,
        public string $image,
        public Deal $deal,
        public Category $category,
        public string $postId,
        public int $offerId,
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

    public function getRooms(): ?int
    {
        return $this->rooms > 0 ? $this->rooms : null;
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
