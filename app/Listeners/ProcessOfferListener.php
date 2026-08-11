<?php

namespace App\Listeners;

use App\Actions\CreatePublicationAction;
use App\DTO\OfferChanged;
use App\Events\OfferCreatedEvent;
use App\Helpers\OfferChangesDetector;
use App\Models\Offer;
use App\Scenarios\ScenarioResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
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
    )
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OfferCreatedEvent $event): void
    {
        $prevOffer = $event->prevOfferId ? Offer::findOrFail($event->prevOfferId) : null;
        $newOffer = Offer::findOrFail($event->newOfferId);
        $changes = $this->offerChangesDetector->detect($prevOffer, $newOffer);
        $offerChanged = new OfferChanged($prevOffer, $newOffer, $changes);

        $scenario = $this->scenarioResolver->resolve($offerChanged);
        Log::channel('job')->info('Сценарий выбран', ['scenario' => $scenario->type()->value]);

        if (is_null($scenario)) {
            return;
        }

        $this->createPublicationAction->execute($newOffer, $scenario);
    }
}
