<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VkPostStat extends Model
{
    protected $table = 'vk_post_stats';

    public $timestamps = true;

    const CREATED_AT = 'datetime';
    const UPDATED_AT = null;

    protected $fillable = ['vk_post_id', 'views', 'reposts', 'likes', 'comments'];

    protected $casts = [
        'datetime' => 'datetime',
    ];

    public function vkWallPost(): BelongsTo
    {
        return $this->belongsTo(VkWallPost::class, 'vk_post_id', 'id');
    }
}
