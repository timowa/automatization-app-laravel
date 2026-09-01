<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Deal;
use App\Enums\OfferStatus;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\VkUser;
use App\Services\Llm\WeeklySummaryLlmGenerator;
use App\Services\Vk\VkApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use VK\Exceptions\VKApiException;

class PublishWeeklySummaryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $agentId
    ) {}

    public function handle(VkApiService $vkApi, WeeklySummaryLlmGenerator $llmGenerator): void
    {
        $agent = Agent::findOrFail($this->agentId);

        /** @var VkUser|null $vkUser */
        $vkUser = $agent->vkUser;
        if (! $vkUser || $vkUser->getToken() === '') {
            Log::channel('job')->warning('Токен недоступен для еженедельной сводки', [
                'agent_id' => $this->agentId,
            ]);

            return;
        }

        $activeOffers = $this->activeOffers($agent->id);

        if ($activeOffers->isEmpty()) {
            return;
        }

        $to = Carbon::now();
        $from = Carbon::now()->subDays(7);

        $activeSale = $this->countByDeal($agent->id, OfferStatus::ACTIVE, Deal::SALE);
        $activeRent = $this->countByDeal($agent->id, OfferStatus::ACTIVE, Deal::RENT_OUT);
        $completedSold = $this->countClosedByDeal($agent->id, Deal::SALE);
        $completedRented = $this->countClosedByDeal($agent->id, Deal::RENT_OUT);

        $imageUrls = [];
        foreach ($activeOffers as $offer) {
            if (count($imageUrls) >= 10) {
                break;
            }

            $firstImage = $offer->images->first();
            if ($firstImage) {
                $imageUrls[] = $firstImage->original_url;
            }
        }

        try {
            $message = $llmGenerator->generate($agent, $from, $to, $activeSale, $activeRent, $completedSold, $completedRented);
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка LLM генерации еженедельной сводки', [
                'agent_id' => $agent->id,
            ]);
            Log::channel('llm')->error($e->getMessage(), [
                'agent_id' => $agent->id,
            ]);

            return;
        }

        try {
            $vkApi->setToken($vkUser->getToken());

            $attachments = [];
            foreach ($imageUrls as $url) {
                $mediaId = $vkApi->uploadWallPhoto($url, (int) $vkUser->vk_user_id);
                if ($mediaId !== null) {
                    $attachments[] = 'photo' . (int) $vkUser->vk_user_id . '_' . $mediaId;
                }
            }

            $result = $vkApi->wallPost((int) $vkUser->vk_user_id, $message, $attachments);

            Log::channel('job')->info('Еженедельная сводка опубликована', [
                'agent_id' => $agent->id,
                'active_sale' => $activeSale,
                'active_rent' => $activeRent,
                'completed_sold' => $completedSold,
                'completed_rented' => $completedRented,
            ]);

            if (empty($result['post_id'])) {
                Log::channel('job')->warning('Еженедельная сводка не содержит post_id', [
                    'agent_id' => $agent->id,
                    'response' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ]);
            }
        } catch (VKApiException $e) {
            Log::channel('job')->warning('Ошибка публикации еженедельной сводки', [
                'agent_id' => $agent->id,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $agent->id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка публикации еженедельной сводки', [
                'agent_id' => $agent->id,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $agent->id,
            ]);
        }
    }

    private function activeOffers(int $agentId): Collection
    {
        return Offer::where('agent_id', $agentId)
            ->where('status', OfferStatus::ACTIVE->value)
            ->get()
            ->unique('code')
            ->values();
    }

    private function countByDeal(int $agentId, OfferStatus $status, Deal $deal): int
    {
        return (int) Offer::where('agent_id', $agentId)
            ->where('status', $status->value)
            ->where('deal', $deal->value)
            ->distinct('code')
            ->count('code');
    }

    private function countClosedByDeal(int $agentId, Deal $deal): int
    {
        return (int) Offer::where('agent_id', $agentId)
            ->where('status', OfferStatus::ARCHIVE->value)
            ->where('deal', $deal->value)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->distinct('code')
            ->count('code');
    }
}
