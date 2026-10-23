<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Deal;
use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Exceptions\StoryPublicationSkippedException;
use App\Exceptions\VkStoryNotSavedException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Interfaces\VkStoriesTemplateInterface;
use App\Jobs\Concerns\MarksPublicationTaskFailedOnJobFailure;
use App\Models\PublicationTask;
use App\Models\VkLoopStory;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Scenarios\TaskDispatcher;
use App\Services\Vk\Stories\Templates\RentStoriesTemplate;
use App\Services\Vk\Stories\Templates\SaleStoriesTemplate;
use App\Services\Vk\Stories\VkStoriesContext;
use App\Services\Vk\Stories\VkStoriesContextFactory;
use App\Services\Vk\Stories\VkStoriesGenerator;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use VK\Exceptions\VKApiException;

class CreateVkLoopStoryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use MarksPublicationTaskFailedOnJobFailure;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        private readonly int $taskId
    ) {}

    public function handle(VkApiService $vkApi,
        PublicationTaskDependencyResolver $taskDependencyResolver,
        TaskDispatcher $taskDispatcher): void
    {
        $task = PublicationTask::findOrFail($this->taskId);

        if ($task->status !== PublicationTaskStatus::QUEUED) {
            return;
        }

        try {
            $task->update(['status' => PublicationTaskStatus::PROCESSING]);

            $parentTask = $task->parentTask;
            $postId = $parentTask->external_id;
            $post = VkWallPost::find($postId);

            if (! $post) {
                throw new NotFoundException('Пост не найден');
            }

            $offer = $post->offer;
            if (! $offer) {
                throw new NotFoundException('Оффер не найден');
            }

            $agent = $offer->agent;
            /** @var VkUser|null $vkUser */
            $vkUser = $agent?->vkUser;

            if (! $vkUser || $vkUser->getToken() === '') {
                throw new NotFoundException('Для агента не задан токен');
            }

            $vkApi->setToken($vkUser->getToken());

            if (! $vkApi->wallPostExists($post->getFullId())) {
                throw new NotFoundException('Пост удалён или не найден во ВКонтакте');
            }

            $context = (new VkStoriesContextFactory)->getContext((int) $post->id);
            $template = $this->resolveTemplate($context);
            $image = (new VkStoriesGenerator)->generate($context, $template);

            try {
                $res = $vkApi->storiesPost($post->getFullId(), $image);

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

            VkLoopStory::updateOrCreate(
                ['offer_id' => $offer->id],
                [
                    'task_id' => $this->taskId,
                    'is_active' => true,
                    'last_published_at' => now(),
                ]
            );

            $task->update(['status' => PublicationTaskStatus::SUCCESS]);

            Log::channel('job')->info('Loop-история по офферу опубликована', [
                'offer_id' => $context->offerId,
                'post_id' => $post->id,
            ]);

            try {
                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
            } catch (\Throwable $e) {
                Log::channel('vk')->error($e->getMessage(), [
                    'task_id' => $this->taskId,
                    'trace' => $e->getTraceAsString(),
                ]);
                Log::channel('job')->warning('Loop-история опубликована, но не удалось продолжить цепочку задач', [
                    'task_id' => $this->taskId,
                ]);
            }
        } catch (StoryPublicationSkippedException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->warning($e->getMessage(), ['task_id' => $this->taskId]);
            Log::channel('job')->warning('Публикация loop-истории пропущена', ['task_id' => $this->taskId]);
        } catch (VkStoryNotSavedException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), ['task_id' => $this->taskId]);
            Log::channel('job')->warning('Ошибка публикации loop-истории по офферу', ['task_id' => $this->taskId]);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка публикации loop-истории по офферу', ['task_id' => $this->taskId]);
        } catch (VKApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage() ?? '',
                'description' => $e->getDescription(),
                'vk_error' => $e->getError(),
            ]);
            Log::channel('job')->warning('Ошибка публикации loop-истории по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Log::channel('job')->warning('Ошибка публикации loop-истории по офферу', ['task_id' => $this->taskId]);
        }
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
