<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OfferStatus;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use App\Services\Vk\WallPost\Templates\WeeklySummaryTemplate;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    ) {
    }

    public function handle(VkApiService $vkApi): void
    {
        $agent = Agent::findOrFail($this->agentId);

        /** @var VkUser|null $vkUser */
        $vkUser = $agent->vkUser;
        if (!$vkUser || $vkUser->getToken() === '') {
            Log::channel('job')->warning('Токен недоступен для еженедельной сводки', [
                'agent_id' => $this->agentId,
            ]);
            return;
        }

        $activeOffers = $this->activeOffers($agent->id);

        if ($activeOffers->isEmpty()) {
            return;
        }

        $closedCount = $this->closedCount($agent->id);

        $images = [];
        foreach ($activeOffers as $offer) {
            if (count($images) >= 10) {
                break;
            }

            $offerImages = $offer->images ?? [];
            if (!empty($offerImages)) {
                $images[] = $offerImages[0];
            }
        }

        $message = (new WeeklySummaryTemplate())->generate($activeOffers, $closedCount, $agent);

        try {
            $vkApi->setToken($vkUser->getToken());
            $result = $vkApi->wallPost((int) $vkUser->vk_user_id, $message, $images);

            Log::channel('job')->info('Еженедельная сводка опубликована', [
                'agent_id' => $agent->id,
                'active_count' => $activeOffers->count(),
                'closed_count' => $closedCount,
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

    private function activeOffers(int $agentId): \Illuminate\Support\Collection
    {
        return Offer::where('agent_id', $agentId)
            ->where('status', OfferStatus::ACTIVE->value)
            ->get()
            ->unique('code')
            ->values();
    }

    private function closedCount(int $agentId): int
    {
        return (int) Offer::where('agent_id', $agentId)
            ->where('status', OfferStatus::ARCHIVE->value)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->distinct('code')
            ->count('code');
    }
}
