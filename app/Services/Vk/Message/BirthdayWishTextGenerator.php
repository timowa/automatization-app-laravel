<?php

declare(strict_types=1);

namespace App\Services\Vk\Message;

use App\Enums\StandardTextKey;
use App\Enums\Vk\Sex;
use App\Services\StandardTextService;

class BirthdayWishTextGenerator
{
    public function __construct(
        private readonly StandardTextService $standardTexts = new StandardTextService,
    ) {}

    public function templateForSex(?int $sex): ?string
    {
        $key = match ($sex) {
            Sex::MALE->value => StandardTextKey::BirthdayWishMale,
            Sex::FEMALE->value => StandardTextKey::BirthdayWishFemale,
            default => null,
        };

        if ($key === null) {
            return null;
        }

        return $this->standardTexts->body($key);
    }
}
