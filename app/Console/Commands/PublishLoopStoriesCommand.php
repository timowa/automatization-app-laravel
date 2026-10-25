<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Deal;
use App\Exceptions\StoryPublicationSkippedException;
use App\Exceptions\VkStoryNotSavedException;
use App\Helpers\VkApiErrorInspector;
use App\Interfaces\VkStoriesTemplateInterface;
use App\Models\VkLoopStory;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\Stories\Templates\RentStoriesTemplate;
use App\Services\Vk\Stories\Templates\SaleStoriesTemplate;
use App\Services\Vk\Stories\VkStoriesContext;
use App\Services\Vk\Stories\VkStoriesContextFactory;
use App\Services\Vk\Stories\VkStoriesGenerator;
use App\Services\Vk\Stories\VkStoryPoster;
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
                if (! $offer) {
                    continue;
                }

                $post = VkWallPost::where('offer_id', $offer->id)->first();
                if (! $post) {
                    $loopStory->update(['is_active' => false]);
                    Log::channel('job')->warning('Повторная публикация истории остановлена: пост не найден', [
                        'offer_id' => $offer->id,
                        'loop_story_id' => $loopStory->id,
                    ]);

                    continue;
                }

                $agent = $offer->agent;
                /** @var VkUser|null $vkUser */
                $vkUser = $agent?->vkUser;

                if (! $vkUser || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
                    Log::channel('vk')->warning('Публикация повторной истории пропущена: нет действующего доступа к странице', [
                        'loop_story_id' => $loopStory->id,
                        'offer_id' => $offer->id,
                    ]);
                    $loopStory->update(['last_published_at' => now()]);

                    continue;
                }

                $vkApi->setToken($vkUser->getToken());

                try {
                    $postExists = $vkApi->wallPostExists($post->getFullId());
                } catch (\Throwable $e) {
                    $loopStory->update(['is_active' => false]);
                    Log::channel('job')->warning('Повторная публикация истории остановлена: не удалось проверить пост', [
                        'loop_story_id' => $loopStory->id,
                        'post_id' => $post->id,
                    ]);
                    Log::channel('vk')->error($e->getMessage(), [
                        'loop_story_id' => $loopStory->id,
                        'post_id' => $post->id,
                    ]);

                    continue;
                }

                if (! $postExists) {
                    Log::channel('job')->warning('Повторная публикация истории остановлена: пост удалён или не найден во ВКонтакте', [
                        'offer_id' => $offer->id,
                        'post_id' => $post->id,
                        'loop_story_id' => $loopStory->id,
                    ]);
                    $loopStory->update(['is_active' => false]);

                    continue;
                }

                $context = (new VkStoriesContextFactory)->getContext((int) $post->id);
                $template = $this->resolveTemplate($context);
                $image = (new VkStoriesGenerator)->generate($context, $template);

                try {
                    $res = (new VkStoryPoster)->post($vkApi, $post->getFullId(), $image);

                    if (! VkApiService::isStorySaveSuccessful($res)) {
                        throw new VkStoryNotSavedException(
                            'ВКонтакте не сохранил историю (count = 0). Ответ загрузки: '
                            .json_encode($res, JSON_UNESCAPED_UNICODE)
                        );
                    }
                } finally {
                    if (is_string($image) && $image !== '' && is_file($image)) {
                        try {
                            unlink($image);
                        } catch (\Throwable) {
                        }
                    }
                }

                $loopStory->update(['last_published_at' => now()]);

                Log::channel('job')->info('Loop-история переопубликована', [
                    'offer_id' => $offer->id,
                    'post_id' => $post->id,
                ]);
            } catch (\Throwable $e) {
                if ($this->shouldStopLoop($e)) {
                    $loopStory->update(['is_active' => false]);
                    Log::channel('vk')->warning($e->getMessage(), [
                        'loop_story_id' => $loopStory->id,
                    ]);
                    Log::channel('job')->warning('Повторная публикация истории остановлена: ссылка на фото недоступна', [
                        'loop_story_id' => $loopStory->id,
                    ]);
                } elseif ($this->shouldPostponeLoopRetry($e)) {
                    $loopStory->update(['last_published_at' => now()]);
                    Log::channel('vk')->warning($e->getMessage(), [
                        'loop_story_id' => $loopStory->id,
                    ]);
                    Log::channel('job')->warning('Повторная публикация истории пропущена', [
                        'loop_story_id' => $loopStory->id,
                    ]);
                } elseif (VkApiErrorInspector::isTimeout($e)) {
                    Log::channel('job')->warning('Повторная публикация истории отложена из-за обрыва связи', [
                        'loop_story_id' => $loopStory->id,
                    ]);
                    Log::channel('vk')->warning($e->getMessage(), [
                        'loop_story_id' => $loopStory->id,
                    ]);
                } else {
                    Log::channel('job')->warning('Ошибка переопубликации loop-истории', [
                        'loop_story_id' => $loopStory->id,
                    ]);
                    Log::channel('vk')->error($e->getMessage(), [
                        'loop_story_id' => $loopStory->id,
                    ]);
                }
            }

            usleep(350_000);
        }

        $this->info('Переопубликация loop-историй завершена');

        return self::SUCCESS;
    }

    private function shouldStopLoop(\Throwable $e): bool
    {
        if (! $e instanceof StoryPublicationSkippedException) {
            return false;
        }

        $message = $e->getMessage();

        return str_contains($message, 'недоступна')
            || str_contains($message, '404');
    }

    private function shouldPostponeLoopRetry(\Throwable $e): bool
    {
        if ($e instanceof VkStoryNotSavedException || $this->shouldStopLoop($e)) {
            return false;
        }

        $message = $e->getMessage();

        return $e instanceof StoryPublicationSkippedException
            || str_contains($message, '404')
            || str_contains($message, 'User authorization failed');
    }

    private function resolveTemplate(VkStoriesContext $context): VkStoriesTemplateInterface
    {
        return match ($context->deal) {
            Deal::SALE => new SaleStoriesTemplate,
            Deal::RENT_OUT => new RentStoriesTemplate,
            default => throw new \RuntimeException('Неподдерживаемый тип сделки для истории: '.$context->getDeal()),
        };
    }
}
