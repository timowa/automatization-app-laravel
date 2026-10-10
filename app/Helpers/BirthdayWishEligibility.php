<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\BirthdayWishGenderStatus;
use App\Enums\Vk\Sex;
use App\Models\Setting;
use App\Services\Vk\Message\BirthdayWishTextGenerator;
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

    public static function isEnabledForSex(Setting $setting, ?int $sex): bool
    {
        return match ($sex) {
            Sex::MALE->value => (bool) $setting->wish_happy_birthday_male,
            Sex::FEMALE->value => (bool) $setting->wish_happy_birthday_female,
            default => false,
        };
    }

    public static function customTextForSex(Setting $setting, ?int $sex): ?string
    {
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

    public static function messageForFriend(
        Setting $setting,
        ?int $sex,
        string $friendName,
        string $agentName,
        ?BirthdayWishTextGenerator $textGenerator = null,
    ): ?string {
        if (! self::isEnabledForSex($setting, $sex)) {
            return null;
        }

        $customText = self::customTextForSex($setting, $sex);
        if ($customText !== null) {
            return $customText;
        }

        $generator = $textGenerator ?? new BirthdayWishTextGenerator;

        return $generator->generate($friendName, $agentName);
    }

    public static function statusForSex(Setting $setting, ?int $sex): BirthdayWishGenderStatus
    {
        if (! self::isEnabledForSex($setting, $sex)) {
            return BirthdayWishGenderStatus::Off;
        }

        return self::customTextForSex($setting, $sex) !== null
            ? BirthdayWishGenderStatus::Custom
            : BirthdayWishGenderStatus::Default;
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
