<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Jobs\Concerns\MarksPublicationTaskFailedOnJobFailure;
use App\Models\Offer;
use App\Models\PublicationTask;
use App\Models\VkLoopStory;
use App\Scenarios\TaskDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EndVkLoopStoryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use MarksPublicationTaskFailedOnJobFailure;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly int $taskId
    ) {}

    public function handle(
        PublicationTaskDependencyResolver $taskDependencyResolver,
        TaskDispatcher $taskDispatcher): void
    {
        $task = PublicationTask::findOrFail($this->taskId);

        if ($task->status !== PublicationTaskStatus::QUEUED) {
            return;
        }

        try {
            $task->update(['status' => PublicationTaskStatus::PROCESSING]);

            $offer = $task->publication?->offer;
            if (! $offer) {
                throw new NotFoundException('Оффер не найден');
            }

            $offerIds = Offer::query()
                ->where('code', $offer->code)
                ->pluck('id');

            VkLoopStory::query()
                ->whereIn('offer_id', $offerIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $task->update(['status' => PublicationTaskStatus::SUCCESS]);

            Log::channel('job')->info('Loop-история по офферу остановлена', [
                'offer_id' => $offer->id,
                'code' => $offer->code,
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
            ]);
            Log::channel('job')->warning('Ошибка остановки loop-истории по офферу', ['task_id' => $this->taskId]);
        }
    }
}
