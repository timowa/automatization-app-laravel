<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTO\OfferData;
use App\Events\OfferCreatedEvent;
use App\Exceptions\OfferParserException;
use App\Helpers\OfferChangesDetector;
use App\Helpers\OfferParser;
use App\Models\Offer;
use App\Models\OfferImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiveOfferWebhookAction
{
    public const SKIP_REASON_DUPLICATE = 'Дубликат существующего оффера';

    public function __construct(
        public OfferParser $offerParser,
        public OfferChangesDetector $offerChangesDetector
    ) {
    }

    public function execute(array $data): void
    {
        $dataOffers = $data['offers'];
        Log::channel('job')->info('new Offer Request', ['count' => count($dataOffers), 'offers' => $dataOffers]);

        foreach ($dataOffers as $v) {
            try {
                $offerData = $this->offerParser->parse($v);
            } catch (OfferParserException $e) {
                Log::channel('job')->warning($e->getMessage(), ['offerData' => $v]);
                $this->persistSkippedOffer($this->offerParser->buildSkippedOfferData($v, $e->getMessage()));
                continue;
            } catch (\Throwable $th) {
                Log::channel('job')->error($th->getMessage(), [
                    'offerData' => $v,
                    'trace' => $th->getTraceAsString(),
                    'file' => $th->getFile(),
                    'line' => $th->getLine(),
                ]);
                $this->persistSkippedOffer(
                    $this->offerParser->buildSkippedOfferData($v, 'Ошибка обработки оффера')
                );
                continue;
            }

            $existingOffer = DB::table('offers')
                ->where('code', $offerData->code)
                ->where('price', $offerData->price)
                ->where('stage', $offerData->stage)
                ->where('status', $offerData->status?->value)
                ->where('agent_id', $offerData->agentId)
                ->first();

            if ($existingOffer) {
                Log::channel('job')->info('Оффер с такими данными уже существует. Пропуск.', ['code' => $offerData->code]);
                $this->persistSkippedOffer(new OfferData(
                    offerId: $offerData->offerId,
                    code: $offerData->code,
                    stage: $offerData->stage,
                    status: $offerData->status,
                    city: $offerData->city,
                    agentId: $offerData->agentId,
                    price: $offerData->price,
                    commission: $offerData->commission,
                    deposit: $offerData->deposit,
                    area: $offerData->area,
                    kitchenArea: $offerData->kitchenArea,
                    livingArea: $offerData->livingArea,
                    rooms: $offerData->rooms,
                    roomsOffered: $offerData->roomsOffered,
                    floor: $offerData->floor,
                    floors: $offerData->floors,
                    imageUrls: $offerData->imageUrls,
                    deal: $offerData->deal,
                    category: $offerData->category,
                    location: $offerData->location,
                    skipReason: self::SKIP_REASON_DUPLICATE,
                    payload: $v,
                ));
                continue;
            }

            $prevOffer = DB::table('offers')
                ->where('code', $offerData->code)
                ->latest('id')
                ->first();

            $offer = $this->persistOffer($offerData);
            Log::channel('job')->info('Offer created', ['code' => $offerData->code, 'id' => $offer->id]);

            event(new OfferCreatedEvent($prevOffer?->id, $offer->id));
        }
    }

    private function persistSkippedOffer(OfferData $offerData): Offer
    {
        $offer = $this->persistOffer($offerData);
        Log::channel('job')->info('Offer saved without tasks', [
            'code' => $offerData->code,
            'id' => $offer->id,
            'skip_reason' => $offerData->skipReason,
        ]);

        return $offer;
    }

    private function persistOffer(OfferData $offerData): Offer
    {
        return DB::transaction(function () use ($offerData): Offer {
            $offer = Offer::create($offerData->getArray());

            $existingMediaIds = $offerData->imageUrls !== [] && $offer->agent_id !== null
                ? OfferImage::whereIn('original_url', $offerData->imageUrls)
                    ->whereRelation('offer', function ($q) use ($offer): void {
                        $q->where('code', $offer->code)
                            ->where('agent_id', $offer->agent_id);
                    })
                    ->whereNotNull('media_id')
                    ->get()
                    ->keyBy('original_url')
                : collect();

            foreach ($offerData->imageUrls as $index => $url) {
                OfferImage::create([
                    'offer_id' => $offer->id,
                    'original_url' => $url,
                    'media_id' => $existingMediaIds->get($url)?->media_id,
                    'sort_order' => $index,
                ]);
            }

            return $offer;
        });
    }
}
