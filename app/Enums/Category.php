<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumHasLabel;

enum Category: int
{
    use EnumHasLabel;

    case APARTMENT = 1;
    case HOUSE = 2;
    case ROOM = 3;
    case NEW_BUILDING = 4;
    case COMMERCIAL = 5;

    public function label(): string
    {
        return match ($this) {
            self::APARTMENT => 'Квартира',
            self::HOUSE => 'Дом на земле',
            self::ROOM => 'Комната',
            self::NEW_BUILDING => 'Новостройка',
            self::COMMERCIAL => 'Коммерческий объект',
        };
    }
}
