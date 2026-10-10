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
    ];

    protected $casts = [
        'wish_happy_birthday_male' => 'boolean',
        'wish_happy_birthday_female' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
