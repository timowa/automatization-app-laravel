<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandardComment extends Model
{
    protected $fillable = [
        'body',
    ];
}
