<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VkWallPost extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'vk_posts';

    public $timestamps = true;

    const CREATED_AT = 'posted_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'offer_id',
        'post_id',
        'owner_id',
        'task_id',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'offer_id', 'id');
    }

    public function getFullId(): string
    {
        return "{$this->owner_id}_{$this->post_id}";
    }
}
