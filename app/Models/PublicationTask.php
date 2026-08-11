<?php

namespace App\Models;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class PublicationTask extends Model
{
    protected $table = 'publication_tasks';
    protected $fillable = [
        'publication_id',
        'type',
        'status',
        'error',
        'external_id',
        'dependent_task_id'
    ];

    protected $casts = [
        'type' => PublicationTaskType::class,
        'status' => PublicationTaskStatus::class,
    ];

    /**
     * @return BelongsTo
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function offer(): HasOneThrough
    {
        return $this->hasOneThrough(
            Offer::class,
            Publication::class,
            'id',
            'id',
            'publication_id',
            'offer_id',
        );
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(PublicationTask::class, 'dependent_task_id', 'id');
    }
}
