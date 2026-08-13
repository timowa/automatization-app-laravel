<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VkLoopStory extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'vk_loop_stories';

    public $timestamps = true;

    protected $fillable = [
        'offer_id',
        'task_id',
        'is_active',
        'last_published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_published_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'offer_id', 'id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PublicationTask::class, 'task_id', 'id');
    }
}
