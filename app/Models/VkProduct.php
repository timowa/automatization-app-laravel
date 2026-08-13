<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VkProduct extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'vk_products';

    public $timestamps = true;

    protected $fillable = [
        'offer_id',
        'agent_id',
        'group_id',
        'product_id',
        'task_id',
        'is_archived',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'product_id' => 'integer',
        'is_archived' => 'boolean',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'offer_id', 'id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PublicationTask::class, 'task_id', 'id');
    }
}
