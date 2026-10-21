<?php

namespace App\Enums;

use App\Traits\EnumHasLabel;

enum OfferStatus: int
{
    use EnumHasLabel;

    case ACTIVE = 1;
    case BOOKED = 2;
    case ARCHIVE = 3;
    case REMOVED = 4;
    case DELAYED = 6;
    case DELETED = 7;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'актив',
            self::BOOKED => 'бронь',
            self::ARCHIVE => 'архив',
            self::REMOVED => 'снято',
            self::DELAYED => 'отложено',
            self::DELETED => 'удалено',
        };
    }

    /**
     * Цвет фона бейджа статуса (белый текст).
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::ACTIVE => '#059669',
            self::BOOKED => '#d97706',
            self::ARCHIVE => '#64748b',
            self::REMOVED => '#dc2626',
            self::DELAYED => '#2563eb',
            self::DELETED => '#4b5563',
        };
    }
}
