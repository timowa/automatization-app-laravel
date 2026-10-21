<?php

declare(strict_types=1);

namespace App\Services\Vk\Message;

use App\Enums\StandardTextKey;
use App\Services\StandardTextService;

final class BirthdayDiscountTextGenerator
{
    public function __construct(
        private readonly StandardTextService $standardTexts = new StandardTextService,
    ) {}

    public function template(): ?string
    {
        return $this->standardTexts->body(StandardTextKey::BirthdayDiscount);
    }
}
