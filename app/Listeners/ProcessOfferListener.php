<?php

namespace App\Listeners;

use App\Actions\CreatePublicationAction;
use App\DTO\OfferChanged;
use App\Events\OfferCreatedEvent;
use App\Helpers\OfferChangesDetector;
use App\Models\Offer;
use App\Models\Publication;
use App\Scenarios\ScenarioResolver;
use App\Services\Exclusive\ExclusivePublicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessOfferListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(
        public OfferChangesDetector $offerChangesDetector,
        public ScenarioResolver $scenarioResolver,
        public CreatePublicationAction $createPublicationAction,
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OfferCreatedEvent $event): void
    {
        $prevOffer = $event->prevOfferId ? Offer::findOrFail($event->prevOfferId) : null;
        $newOffer = Offer::findOrFail($event->newOfferId);
        if ($newOffer->exclusive_transition) {
            if ($newOffer->exclusive_skip_reason !== null) {
                return;
            }
            $source = Publication::whereNull('exclusive_run_id')
                ->whereHas('offer', fn ($q) => $q->where('code', $newOffer->code)->where('id', '<', $newOffer->id))
                ->latest('id')->first();
            $service = app(ExclusivePublicationService::class);
            $run = $service->start($newOffer, $source, 'transition');
            if ($run !== null) {
                $service->expand($run);
            }

            return;
        }
        $changes = $this->offerChangesDetector->detect($prevOffer, $newOffer);
        $offerChanged = new OfferChanged($prevOffer, $newOffer, $changes);

        $scenario = $this->scenarioResolver->resolve($offerChanged);

        if (is_null($scenario)) {
            Log::channel('job')->warning('Сценарий не был выбран');
            $newOffer->update(['skip_reason' => 'Сценарий не выбран']);

            return;
        }

        Log::channel('job')->info('Сценарий выбран', ['scenario' => $scenario->type()->value]);

        $this->createPublicationAction->execute($newOffer, $scenario);

        Log::channel('job')->info('Публикация и задачи созданы', [
            'offer_id' => $newOffer->id,
            'scenario' => $scenario->type()->value,
        ]);
    }
}
