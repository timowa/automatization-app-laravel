<?php

namespace App\Models;

use App\Enums\PublicationTaskStatus;
use App\Enums\PublicationTaskType;
use App\Jobs\CheckPublicationCompletionJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicationTask extends Model
{
    use HasFactory;

    protected $table = 'publication_tasks';

    protected $fillable = [
        'publication_id',
        'type',
        'status',
        'error',
        'external_id',
        'external_started_at',
        'dependent_task_id',
    ];

    protected $casts = [
        'external_started_at' => 'datetime',
        'type' => PublicationTaskType::class,
        'status' => PublicationTaskStatus::class,
    ];

    protected static function booted(): void
    {
        static::updated(function (self $task): void {
            if ($task->wasChanged('status') && in_array($task->status, [PublicationTaskStatus::SUCCESS, PublicationTaskStatus::FAILED], true)) {
                // Проверка выполняется отдельно: сбой очереди не меняет результат задачи.
                DB::afterCommit(function () use ($task): void {
                    try {
                        CheckPublicationCompletionJob::dispatch($task->publication_id);
                    } catch (\Throwable $e) {
                        Log::channel('job')->warning('Не удалось запланировать проверку публикации', ['publication_id' => $task->publication_id]);
                    }
                });
            }
        });
    }

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

    public function wallPost(): HasOne
    {
        return $this->hasOne(VkWallPost::class, 'task_id');
    }
}
