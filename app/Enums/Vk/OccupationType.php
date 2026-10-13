<?php

declare(strict_types=1);

namespace App\Enums\Vk;

enum OccupationType: string
{
    case WORK = 'work';
    case SCHOOL = 'school';
    case UNIVERSITY = 'university';

    public function label(): string
    {
        return match ($this) {
            self::WORK => 'Работа',
            self::SCHOOL => 'Школа',
            self::UNIVERSITY => 'Вуз',
        };
    }

    public static function labelFor(?string $value): ?string
    {
        return $value === null || $value === '' ? null : self::tryFrom($value)?->label();
    }
}
