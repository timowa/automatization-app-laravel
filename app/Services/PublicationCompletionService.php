<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PublicationTaskStatus as Status;
use App\Models\ExclusivePublicationRun;
use App\Models\Publication;
use App\Services\Exclusive\ExclusivePublicationService;
use Illuminate\Support\Facades\DB;

final class PublicationCompletionService
{
    public function check(int $publicationId): void
    {
        $run = DB::transaction(function () use ($publicationId): ?ExclusivePublicationRun {
            $publication = Publication::whereKey($publicationId)->lockForUpdate()->first();
            if ($publication === null) {
                return null;
            }
            // Ошибка родителя завершает также всю заблокированную цепочку.
            do {
                $failed = $publication->tasks()->where('status', Status::FAILED)->pluck('id');
                $changed = $publication->tasks()->whereIn('dependent_task_id', $failed)
                    ->where('status', Status::WAITING)->update([
                        'status' => Status::FAILED, 'error' => 'Не выполнено предыдущее действие',
                    ]);
            } while ($changed > 0);
            if ($publication->tasks()->whereNotIn('status', [Status::SUCCESS, Status::FAILED])->exists()) {
                return null;
            }
            if ($publication->exclusive_run_id !== null) {
                $run = ExclusivePublicationRun::whereKey($publication->exclusive_run_id)->lockForUpdate()->firstOrFail();
                $tasks = DB::table('publication_tasks')->join('publications', 'publications.id', '=', 'publication_tasks.publication_id')
                    ->where('publications.exclusive_run_id', $run->id);
                if (! (clone $tasks)->whereNotIn('status', ['success', 'failed'])->exists()) {
                    $run->update(['status' => (clone $tasks)->where('status', 'failed')->exists() ? 'failed' : 'completed', 'completed_at' => now()]);
                }

                return null;
            }
            if ($publication->exclusive_checked_at !== null) {
                return null;
            }
            $run = $publication->offer->exclusive
                ? app(ExclusivePublicationService::class)->start($publication->offer, $publication, 'completion') : null;
            $publication->update(['exclusive_checked_at' => now()]);

            return $run;
        }, 3);
        if ($run !== null) {
            app(ExclusivePublicationService::class)->expand($run);
        }
    }
}
