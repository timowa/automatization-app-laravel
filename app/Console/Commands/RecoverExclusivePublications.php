<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckPublicationCompletionJob;
use App\Models\ExclusivePublicationRun;
use App\Models\Offer;
use App\Models\Publication;
use App\Models\PublicationTask;
use App\Scenarios\TaskDispatcher;
use App\Services\Exclusive\ExclusivePublicationService;
use Illuminate\Console\Command;

final class RecoverExclusivePublications extends Command
{
    protected $signature = 'publications:recover-exclusive';

    protected $description = 'Продолжить незавершённые эксклюзивные публикации';

    public function handle(ExclusivePublicationService $service): void
    {
        // Восстановление после остановки процесса между отметкой queued и отправкой в очередь.
        PublicationTask::where('status', 'queued')->where('updated_at', '<', now()->subMinutes(15))
            ->whereHas('publication', fn ($q) => $q->whereNotNull('exclusive_run_id'))
            ->update(['status' => 'pending']);
        Publication::whereNull('exclusive_checked_at')->whereNull('exclusive_run_id')
            ->whereHas('offer', fn ($q) => $q->where('exclusive', true))
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    CheckPublicationCompletionJob::dispatch($row->id);
                }
            });
        Offer::where('exclusive_transition', true)->whereNull('exclusive_skip_reason')->whereDoesntHave('exclusiveRuns')
            ->chunkById(100, function ($offers) use ($service): void {
                foreach ($offers as $offer) {
                    $source = Publication::whereNull('exclusive_run_id')->whereHas('offer', fn ($q) => $q->where('code', $offer->code)->where('id', '<', $offer->id))->latest('id')->first();
                    $service->start($offer, $source, 'transition');
                }
            });
        ExclusivePublicationRun::where('status', 'pending')->each(fn ($run) => $service->expand($run));
        Publication::whereNotNull('exclusive_run_id')->whereHas('tasks', fn ($q) => $q->where('status', 'pending'))
            ->each(fn ($publication) => app(TaskDispatcher::class)->dispatch($publication->id));
        Publication::whereNotNull('exclusive_run_id')->whereHas('exclusiveRun', fn ($q) => $q->where('status', 'processing'))
            ->each(fn ($publication) => CheckPublicationCompletionJob::dispatch($publication->id));
    }
}
