<?php

declare(strict_types=1);

namespace App\Traits;

trait EnumHasLabel
{
    public static function tryFromLabel(?string $label): ?self
    {
        if ($label === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($label));
        if ($normalized === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if (method_exists($case, 'label') && mb_strtolower($case->label()) === $normalized) {
                return $case;
            }

            if (method_exists($case, 'aliases')) {
                foreach ($case->aliases() as $alias) {
                    if (mb_strtolower($alias) === $normalized) {
                        return $case;
                    }
                }
            }
        }

        return null;
    }
}
