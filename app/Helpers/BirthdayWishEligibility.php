<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\Vk\Sex;
use App\Models\Setting;
use Carbon\Carbon;
use DateTimeInterface;

final class BirthdayWishEligibility
{
    public static function hasFullBirthDate(?string $bdate): bool
    {
        return is_string($bdate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bdate) === 1;
    }

    public static function ageInRange(?string $bdate, ?DateTimeInterface $on = null): bool
    {
        if (! self::hasFullBirthDate($bdate)) {
            return false;
        }

        $onDate = Carbon::parse($on ?? now())->startOfDay();
        $birthDate = Carbon::createFromFormat('Y-m-d', $bdate)->startOfDay();
        $age = $birthDate->diff($onDate)->y;

        return $age >= 18 && $age <= 70;
    }

    public static function messageForFriend(Setting $setting, ?int $sex): ?string
    {
        if (! $setting->wish_happy_birthday) {
            return null;
        }

        $raw = match ($sex) {
            Sex::MALE->value => $setting->birthday_wish_male_text,
            Sex::FEMALE->value => $setting->birthday_wish_female_text,
            default => null,
        };

        if (! is_string($raw)) {
            return null;
        }

        $text = trim($raw);

        return $text === '' ? null : $text;
    }

    public static function hasMaleText(Setting $setting): bool
    {
        return is_string($setting->birthday_wish_male_text)
            && trim($setting->birthday_wish_male_text) !== '';
    }

    public static function hasFemaleText(Setting $setting): bool
    {
        return is_string($setting->birthday_wish_female_text)
            && trim($setting->birthday_wish_female_text) !== '';
    }

    public static function wasWishedThisYear(mixed $lastBirthdayWishAt, ?DateTimeInterface $on = null): bool
    {
        if ($lastBirthdayWishAt === null || $lastBirthdayWishAt === '') {
            return false;
        }

        $onYear = Carbon::parse($on ?? now())->year;
        $wishYear = Carbon::parse($lastBirthdayWishAt)->year;

        return $wishYear === $onYear;
    }
}
