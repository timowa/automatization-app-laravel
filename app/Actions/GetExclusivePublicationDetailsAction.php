<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ExclusivePublicationRun;
use App\Models\Offer;
use App\Services\Exclusive\ExclusiveStatusPresenter;

final class GetExclusivePublicationDetailsAction
{
    public function __construct(private readonly ExclusiveStatusPresenter $presenter) {}

    public function execute(Offer $offer): array
    {
        $offer->load(['publication.tasks', 'exclusiveRuns.publications.agent.vkUser', 'exclusiveRuns.publications.tasks.wallPost']);
        $query = ExclusivePublicationRun::where('code', $offer->code)->where('offer_id', '!=', $offer->id);
        if (preg_match('/запуск №(\d+)/u', $offer->exclusive_skip_reason ?? '', $matches)) {
            $query->whereKey((int) $matches[1]);
        }

        return [
            'offer' => $offer,
            'status' => $this->presenter->label($offer),
            'previousRun' => $query->latest('started_at')->first(),
        ];
    }
}
