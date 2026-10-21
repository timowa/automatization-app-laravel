<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PublicationTaskStatus;
use App\Models\Offer;
use App\Models\PublicationTask;

final class OfferHealthStatusResolver
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_PROCESSING = 'processing';

    public function __construct(
        private readonly PublicationTaskErrorPresenter $errorPresenter = new PublicationTaskErrorPresenter,
    ) {
    }

    /**
     * @return array{status: string, tooltip: string, can_retry: bool}
     */
    public function resolve(Offer $offer): array
    {
        $publication = $offer->relationLoaded('publication')
            ? $offer->publication
            : $offer->publication()->with('tasks')->first();

        if ($publication !== null && ! $publication->relationLoaded('tasks')) {
            $publication->load('tasks');
        }

        /** @var \Illuminate\Support\Collection<int, PublicationTask> $tasks */
        $tasks = $publication?->tasks ?? collect();

        $failedTasks = $tasks->filter(
            fn (PublicationTask $task): bool => $task->status === PublicationTaskStatus::FAILED
        );

        $inProgress = $tasks->filter(
            fn (PublicationTask $task): bool => in_array(
                $task->status,
                [
                    PublicationTaskStatus::PENDING,
                    PublicationTaskStatus::QUEUED,
                    PublicationTaskStatus::PROCESSING,
                    PublicationTaskStatus::WAITING,
                ],
                true
            )
        );

        $canRetry = $failedTasks->isNotEmpty();

        if ($offer->skip_reason) {
            return [
                'status' => self::STATUS_WARNING,
                'tooltip' => (string) $offer->skip_reason,
                'can_retry' => $canRetry,
            ];
        }

        if ($publication === null || $tasks->isEmpty()) {
            return [
                'status' => self::STATUS_WARNING,
                'tooltip' => 'Оффер записан, но задачи не были созданы',
                'can_retry' => false,
            ];
        }

        $unfinished = $tasks->filter(
            fn (PublicationTask $task): bool => $task->status !== PublicationTaskStatus::SUCCESS
        );

        if ($unfinished->isEmpty()) {
            return [
                'status' => self::STATUS_OK,
                'tooltip' => 'Все задачи выполнены успешно',
                'can_retry' => false,
            ];
        }

        if ($failedTasks->isNotEmpty()) {
            $parts = [];
            foreach ($failedTasks as $task) {
                $parts[] = sprintf(
                    '%s не выполнен: %s',
                    $task->type->label(),
                    $this->errorPresenter->present($task->error)
                );
            }

            return [
                'status' => self::STATUS_WARNING,
                'tooltip' => implode('; ', $parts),
                'can_retry' => true,
            ];
        }

        if ($inProgress->isNotEmpty()) {
            return [
                'status' => self::STATUS_PROCESSING,
                'tooltip' => 'Задачи ещё выполняются',
                'can_retry' => false,
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'tooltip' => 'Есть незавершённые задачи',
            'can_retry' => false,
        ];
    }
}
