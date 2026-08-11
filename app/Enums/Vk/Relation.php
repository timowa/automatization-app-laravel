<?php

declare(strict_types=1);

namespace App\Enums\Vk;

use App\Traits\EnumHasLabel;

enum Relation: int
{
    use EnumHasLabel;

    case UNSPECIFIED = 0;
    case SINGLE = 1;
    case HAS_FRIEND = 2;
    case ENGAGED = 3;
    case MARRIED = 4;
    case COMPLICATED = 5;
    case ACTIVE_SEARCH = 6;
    case IN_LOVE = 7;
    case CIVIL_MARRIAGE = 8;

    public function label(): string
    {
        return match ($this) {
            self::UNSPECIFIED => 'Не указано',
            self::SINGLE => 'Не женат / не замужем',
            self::HAS_FRIEND => 'Есть друг / есть подруга',
            self::ENGAGED => 'Помолвлен / помолвлена',
            self::MARRIED => 'Женат / замужем',
            self::COMPLICATED => 'Всё сложно',
            self::ACTIVE_SEARCH => 'В активном поиске',
            self::IN_LOVE => 'Влюблён / влюблена',
            self::CIVIL_MARRIAGE => 'В гражданском браке',
        };
    }

    public static function labelFor(?int $value): ?string
    {
        return $value === null ? null : self::tryFrom($value)?->label();
    }
}
