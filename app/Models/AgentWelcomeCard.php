<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AgentWelcomeCard extends Model
{
    protected $table = 'agent_welcome_cards';

    protected $fillable = [
        'agent_id',
        'path',
        'thumb_path',
        'media_id',
    ];

    protected $casts = [
        'agent_id' => 'integer',
        'media_id' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function previewUrl(): string
    {
        $path = $this->thumb_path !== '' ? $this->thumb_path : $this->path;

        return $path !== '' ? Storage::disk('public')->url($path) : '';
    }

    public function originalUrl(): string
    {
        return $this->path !== '' ? Storage::disk('public')->url($this->path) : '';
    }
}
