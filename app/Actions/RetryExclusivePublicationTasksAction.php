<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Offer;
use App\Models\Publication;

final class RetryExclusivePublicationTasksAction
{
    public function __construct(private readonly RetryPublicationTasksAction $retry) {}

    public function execute(Offer $offer, ?int $publicationId): int
    {
        $query = Publication::where('offer_id', $offer->id)->whereNotNull('exclusive_run_id');
        $publications = $publicationId === null ? $query->get() : collect([$query->findOrFail($publicationId)]);
        $count = 0;
        foreach ($publications as $publication) {
            $count += $this->retry->execute($publication);
        }

        return $count;
    }
}
