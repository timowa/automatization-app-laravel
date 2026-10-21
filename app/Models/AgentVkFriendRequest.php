<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentVkFriendRequest extends Model
{
    public const AGENT_ALREADY_MESSAGED_SENTINEL = 1;

    protected $table = 'agent_vk_friend_requests';

    protected $fillable = [
        'agent_id',
        'user_id',
        'first_name',
        'last_name',
        'is_closed',
        'can_access_closed',
        'deactivated',
        'mutual_count',
        'track_code',
        'payload',
        'message_id',
    ];

    protected $casts = [
        'agent_id' => 'integer',
        'user_id' => 'integer',
        'is_closed' => 'boolean',
        'can_access_closed' => 'boolean',
        'mutual_count' => 'integer',
        'payload' => 'array',
        'message_id' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
