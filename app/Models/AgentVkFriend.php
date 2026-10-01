<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentVkFriend extends Model
{
    protected $table = 'agents_vk_friends';

    protected $fillable = [
        'agent_id',
        'user_id',
        'bdate',
        'first_name',
        'last_name',
        'middle_name',
    ];

    protected $casts = [
        'agent_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
