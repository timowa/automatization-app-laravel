<?php

namespace App\Models;

use App\Enums\ScenarioType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publication extends Model
{
    use HasFactory;

    protected $table = 'publications';

    protected $fillable = [
        'agent_id',
        'exclusive_run_id',
        'exclusive_checked_at',
        'offer_id',
        'scenario',
    ];

    protected $casts = [
        'scenario' => ScenarioType::class,
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function exclusiveRun(): BelongsTo
    {
        return $this->belongsTo(ExclusivePublicationRun::class, 'exclusive_run_id');
    }

    public function publishingAgent(): ?Agent
    {
        return $this->agent_id !== null ? $this->agent : $this->offer?->agent;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PublicationTask::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
