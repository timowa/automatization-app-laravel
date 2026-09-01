<?php

namespace App\Actions;

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
    public function __construct(
        public OfferParser $offerParser,
        public OfferChangesDetector $offerChangesDetector
    )
    {

    }

    public function execute(array $data): void
    {
        $dataOffers = $data['offers'];
        Log::channel('job')->info('new Offer Request', ['count' => count($dataOffers)]);

        foreach ($dataOffers as $v) {
            try {
                $offerData = $this->offerParser->parse($v);
            } catch (OfferParserException $e) {
                Log::channel('job')->warning($e->getMessage(), ['offerData' => $v]);
                continue;
            } catch (\Throwable $th) {
                Log::channel('job')->error($th->getMessage(), ['offerData' => $v, 'trace' => $th->getTraceAsString(), 'file' => $th->getFile(), 'line' => $th->getLine()]);
                continue;
            }

            $existingOffer = DB::table('offers')
                ->where('code', $offerData->code)
                ->where('price', $offerData->price)
                ->where('stage', $offerData->stage)
                ->where('status', $offerData->status->value)
                ->first();

            if ($existingOffer) {
                Log::channel('job')->info('Оффер с такими данными уже существует. Пропуск.', ['code' => $offerData->code]);
                continue;
            }

            $prevOffer = DB::table('offers')
                ->where('code', $offerData->code)
                ->latest('id')
                ->first();

            $offer = DB::transaction(function () use ($offerData): Offer {
                $offer = Offer::create($offerData->getArray());

                $existingMediaIds = $offerData->imageUrls !== []
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
            Log::channel('job')->info('Offer created', ['code' => $offerData->code, 'id' => $offer->id]);

            event(new OfferCreatedEvent($prevOffer?->id, $offer->id));
        }
    }
}
