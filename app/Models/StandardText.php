<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StandardTextKey;
use Illuminate\Database\Eloquent\Model;

class StandardText extends Model
{
    protected $fillable = [
        'key',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'key' => StandardTextKey::class,
        ];
    }
}
