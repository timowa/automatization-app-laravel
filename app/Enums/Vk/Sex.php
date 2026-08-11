<?php

declare(strict_types=1);

namespace App\Enums\Vk;

use App\Traits\EnumHasLabel;

enum Sex: int
{
    use EnumHasLabel;

    case UNSPECIFIED = 0;
    case FEMALE = 1;
    case MALE = 2;

    public function label(): string
    {
        return match ($this) {
            self::UNSPECIFIED => 'Не указан',
            self::FEMALE => 'Женский',
            self::MALE => 'Мужской',
        };
    }

    public static function labelFor(?int $value): ?string
    {
        return $value === null ? null : self::tryFrom($value)?->label();
    }
}
