<?php

declare(strict_types=1);

namespace App\Enums;

enum BirthdayWishGenderStatus: string
{
    case Off = 'off';
    case Custom = 'custom';
    case Default = 'default';
}
