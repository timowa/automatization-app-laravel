<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VkGroup extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'vk_groups';

    public $timestamps = false;

    protected $fillable = ['group_id', 'city'];

    protected $casts = [
        'group_id' => 'integer',
        'city' => 'integer',
    ];
}
