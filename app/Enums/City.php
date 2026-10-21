<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumHasLabel;

enum City: int
{
    use EnumHasLabel;

    case ABAKAN = 1;
    case KYZYL = 2;

    public function label(): string
    {
        return match ($this) {
            self::ABAKAN => 'Абакан',
            self::KYZYL => 'Кызыл',
        };
    }

    public function alias(): string
    {
        return match ($this) {
            self::ABAKAN => 'abakan',
            self::KYZYL => 'kyzyl',
        };
    }

    /**
     * Альтернативные названия, которые мапятся на этот город.
     *
     * @return string[]
     */
    public function aliases(): array
    {
        return match ($this) {
            self::ABAKAN => ['Черногорск', 'Усть-Абакан'],
            self::KYZYL => ['Кызылский кожуун'],
        };
    }

    /**
     * Города групп, в которые можно публиковать оффер с этим городом.
     * Абаканские офферы — во все группы; кызыльские — только в кызыльские.
     *
     * @return self[]
     */
    public function acceptedGroupCities(): array
    {
        return match ($this) {
            self::ABAKAN => self::cases(),
            self::KYZYL => [self::KYZYL],
        };
    }
}
