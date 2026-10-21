<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = [
        'agent_id',
        'wish_happy_birthday_male',
        'wish_happy_birthday_female',
        'birthday_wish_male_text',
        'birthday_wish_female_text',
        'friend_request_welcome_enabled',
        'friend_request_welcome_text',
        'friend_request_card_id',
    ];

    protected $casts = [
        'wish_happy_birthday_male' => 'boolean',
        'wish_happy_birthday_female' => 'boolean',
        'friend_request_welcome_enabled' => 'boolean',
        'friend_request_card_id' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function welcomeCard(): BelongsTo
    {
        return $this->belongsTo(AgentWelcomeCard::class, 'friend_request_card_id');
    }

    public function hasWelcomeCard(): bool
    {
        return $this->welcomeCard !== null && (int) $this->welcomeCard->media_id > 0;
    }
}
