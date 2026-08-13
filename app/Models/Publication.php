<?php

namespace App\Models;

use App\Enums\ScenarioType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publication extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'publications';
    protected $fillable = [
        'offer_id',
        'scenario'
    ];

    protected $casts = [
        'scenario' => ScenarioType::class
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(PublicationTask::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
