<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumHasLabel;

enum Deal: int
{
    use EnumHasLabel;

    case SALE = 1;
    case PURCHASE = 2;
    case RENT_OUT = 3;
    case RENT = 4;

    public function label(): string
    {
        return match ($this) {
            self::SALE => 'Продажа',
            self::PURCHASE => 'Покупка',
            self::RENT_OUT => 'Сдача',
            self::RENT => 'Съем',
        };
    }
}
