<?php

namespace App\Models;

use App\Enums\ScenarioType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Publication extends Model
{
    protected $table = 'publications';
    protected $fillable = [
        'offer_id',
        'scenario'
    ];

    protected $casts = [
        'scenario' => ScenarioType::class
    ];

    /**
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(PublicationTask::class);
    }

    public function offer(): BelongsTo
    {
        return $this->BelongsTo(Offer::class);
    }
}
