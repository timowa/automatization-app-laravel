<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Deal;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\Stories\Templates\RentStoriesTemplate;
use App\Services\Vk\Stories\Templates\SaleStoriesTemplate;
use App\Services\Vk\Stories\VkStoriesContextFactory;
use App\Services\Vk\Stories\VkStoriesGenerator;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateVkStoriesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly int $postId)
    {
    }

    public function handle(VkApiService $vkApi): void
    {
        $post = VkWallPost::findOrFail($this->postId);
        $offer = $post->offer;
        $agent = $offer?->agent;
        /** @var VkUser|null $vkUser */
        $vkUser = $agent?->vkUser;

        if (!$vkUser || $vkUser->getToken() === '') {
            throw new \RuntimeException('Для агента не задан токен');
        }

        $vkApi->setToken($vkUser->getToken());

        $context = (new VkStoriesContextFactory())->getContext($this->postId);
        $template = $this->resolveTemplate($context);
        $image = (new VkStoriesGenerator())->generate($context, $template);

        $res = $vkApi->storiesPost($context->postId, $image);

        if (($res['count'] ?? 0) < 1) {
            throw new \RuntimeException(json_encode($res, JSON_UNESCAPED_UNICODE));
        }

        unlink($image);

        Log::channel('vk')->info('История по офферу опубликована', [
            'offer_id' => $context->offerId,
            'post_id' => $this->postId,
        ]);
    }

    private function resolveTemplate(\App\Services\Vk\Stories\VkStoriesContext $context): \App\Interfaces\VkStoriesTemplateInterface
    {
        return match ($context->deal) {
            Deal::SALE => new SaleStoriesTemplate(),
            Deal::RENT_OUT => new RentStoriesTemplate(),
            default => throw new \RuntimeException('Неподдерживаемый тип сделки для истории: ' . $context->getDeal()),
        };
    }
}
