<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Deal;
use App\Models\VkLoopStory;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\Stories\Templates\RentStoriesTemplate;
use App\Services\Vk\Stories\Templates\SaleStoriesTemplate;
use App\Services\Vk\Stories\VkStoriesContextFactory;
use App\Services\Vk\Stories\VkStoriesGenerator;
use App\Services\Vk\VkApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishLoopStoriesCommand extends Command
{
    protected $signature = 'vk:publish-loop-stories';
    protected $description = 'Публикация активных loop-историй раз в 3 дня';

    public function handle(VkApiService $vkApi): int
    {
        $threshold = Carbon::now()->subDays(3);

        $loopStories = VkLoopStory::query()
            ->where('is_active', true)
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_published_at')
                    ->orWhere('last_published_at', '<', $threshold);
            })
            ->get();

        if ($loopStories->isEmpty()) {
            $this->info('Нет активных loop-историй для публикации');
            return self::SUCCESS;
        }

        foreach ($loopStories as $loopStory) {
            try {
                $offer = $loopStory->offer;
                if (!$offer) {
                    continue;
                }

                $post = VkWallPost::where('offer_id', $offer->id)->first();
                if (!$post) {
                    Log::channel('job')->warning('Не найден пост для loop-истории', [
                        'offer_id' => $offer->id,
                    ]);
                    continue;
                }

                $agent = $offer->agent;
                /** @var VkUser|null $vkUser */
                $vkUser = $agent?->vkUser;

                if (!$vkUser || $vkUser->getToken() === '') {
                    Log::channel('job')->warning('Не задан токен для loop-истории', [
                        'offer_id' => $offer->id,
                    ]);
                    continue;
                }

                $vkApi->setToken($vkUser->getToken());

                $context = (new VkStoriesContextFactory())->getContext((int) $post->id);
                $template = $this->resolveTemplate($context);
                $image = (new VkStoriesGenerator())->generate($context, $template);

                $res = $vkApi->storiesPost($post->getFullId(), $image);

                if (($res['count'] ?? 0) < 1) {
                    throw new \RuntimeException(json_encode($res, JSON_UNESCAPED_UNICODE));
                }

                unlink($image);

                $loopStory->update(['last_published_at' => now()]);

                Log::channel('job')->info('Loop-история переопубликована', [
                    'offer_id' => $offer->id,
                    'post_id' => $post->id,
                ]);
            } catch (\Throwable $e) {
                Log::channel('job')->warning('Ошибка переопубликации loop-истории', [
                    'loop_story_id' => $loopStory->id,
                ]);
                Log::channel('vk')->error($e->getMessage(), [
                    'loop_story_id' => $loopStory->id,
                ]);
            }

            usleep(350_000);
        }

        $this->info('Переопубликация loop-историй завершена');
        return self::SUCCESS;
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
