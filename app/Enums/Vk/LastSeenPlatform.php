<?php

declare(strict_types=1);

namespace App\Enums\Vk;

use App\Traits\EnumHasLabel;

enum LastSeenPlatform: int
{
    use EnumHasLabel;

    case MOBILE_WEB = 1;
    case IPHONE = 2;
    case IPAD = 3;
    case ANDROID = 4;
    case WINDOWS_PHONE = 5;
    case WINDOWS_10 = 6;
    case FULL_WEB = 7;

    public function label(): string
    {
        return match ($this) {
            self::MOBILE_WEB => 'Мобильная версия',
            self::IPHONE => 'Приложение для iPhone',
            self::IPAD => 'Приложение для iPad',
            self::ANDROID => 'Приложение для Android',
            self::WINDOWS_PHONE => 'Приложение для Windows Phone',
            self::WINDOWS_10 => 'Приложение для Windows 10',
            self::FULL_WEB => 'Полная версия сайта',
        };
    }

    public static function labelFor(?int $value): ?string
    {
        return $value === null ? null : self::tryFrom($value)?->label();
    }
}
