<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumHasLabel;

enum City: int
{
    use EnumHasLabel;

    case ABAKAN = 1;
    case KYZYL = 2;
    case CHIKAGO = 3;

    public function label(): string
    {
        return match ($this) {
            self::ABAKAN => 'Абакан',
            self::KYZYL => 'Кызыл',
            self::CHIKAGO => 'Черногорск',
        };
    }

    public function alias(): string
    {
        return match ($this) {
            self::ABAKAN => 'abakan',
            self::KYZYL => 'kyzyl',
            self::CHIKAGO => 'chernogorsk',
        };
    }
}
