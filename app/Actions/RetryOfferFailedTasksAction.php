<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PublicationTaskStatus;
use App\Models\Offer;
use App\Models\PublicationTask;
use App\Services\OfferHealthStatusResolver;
use RuntimeException;

final class RetryOfferFailedTasksAction
{
    public function __construct(
        private readonly RetryPublicationTasksAction $retry,
        private readonly OfferHealthStatusResolver $healthStatusResolver,
    ) {}

    /**
     * @return array{status: string, tooltip: string, can_retry: bool, retried: int}
     */
    public function execute(Offer $offer): array
    {
        $offer->load(['publication.tasks']);

        $publication = $offer->publication;
        if ($publication === null) {
            throw new RuntimeException('У оффера нет задач для повтора');
        }

        $failedTasks = $publication->tasks->filter(
            fn (PublicationTask $task): bool => $task->status === PublicationTaskStatus::FAILED
        );

        if ($failedTasks->isEmpty()) {
            throw new RuntimeException('Нет задач с ошибкой');
        }

        $retried = $this->retry->execute($publication);

        $offer->refresh()->load(['publication.tasks']);
        $health = $this->healthStatusResolver->resolve($offer);

        return [
            ...$health,
            'retried' => $retried,
        ];
    }
}
