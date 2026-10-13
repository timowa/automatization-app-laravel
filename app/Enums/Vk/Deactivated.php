<?php

declare(strict_types=1);

namespace App\Enums\Vk;

enum Deactivated: string
{
    case DELETED = 'deleted';
    case BANNED = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::DELETED => 'Удалена',
            self::BANNED => 'Заблокирована',
        };
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? 'Активна';
    }
}
