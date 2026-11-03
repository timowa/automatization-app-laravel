<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScenarioType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExclusivePublicationRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scenario' => ScenarioType::class,
        'recipient_ids' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'exclusive_run_id');
    }
}
