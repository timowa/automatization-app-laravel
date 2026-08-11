<?php

declare(strict_types=1);

namespace App\Traits;

trait EnumHasLabel
{
    public static function tryFromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if (method_exists($case, 'label') && mb_strtolower($case->label()) === mb_strtolower($label)) {
                return $case;
            }
        }

        return null;
    }
}
