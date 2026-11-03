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
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Scenarios\TaskDispatcher;
use App\Services\Vk\Stories\Templates\RentStoriesTemplate;
use App\Services\Vk\Stories\Templates\SaleStoriesTemplate;
use App\Services\Vk\Stories\VkStoriesContext;
use App\Services\Vk\Stories\VkStoriesContextFactory;
use App\Services\Vk\Stories\VkStoriesGenerator;
use App\Services\Vk\Stories\VkStoryPoster;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use VK\Exceptions\VKApiException;

class CreateVkStoriesJob implements ShouldQueue
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

    public function middleware(): array
    {
        return [(new WithoutOverlapping('publication-task:'.$this->taskId))->shared()->dontRelease()->expireAfter(300)];
    }

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

            $agent = $task->publication->publishingAgent();
            /** @var VkUser|null $vkUser */
            $vkUser = $agent?->vkUser;

            if (! $vkUser || $vkUser->getToken() === '') {
                throw new NotFoundException('Для агента не задан токен');
            }

            $vkApi->setToken($vkUser->getToken());

            if (! $vkApi->wallPostExists($post->getFullId())) {
                throw new NotFoundException('Пост удалён или не найден во ВКонтакте');
            }

            if ($task->publication->exclusive_run_id !== null) {
                $existing = $vkApi->findStoryForPost((int) $vkUser->vk_user_id, $post->getFullId());
                if ($existing !== null) {
                    $task->update(['status' => PublicationTaskStatus::SUCCESS, 'external_id' => $existing['owner_id'].'_'.$existing['id']]);
                    $taskDependencyResolver->release($this->taskId);
                    $taskDispatcher->dispatch($task->publication_id);

                    return;
                }
                if ($task->external_started_at?->lte(now()->subDay())) {
                    throw new NotFoundException('Результат прежней отправки истории неизвестен, а срок её доступности истёк. Требуется проверить публикацию вручную.');
                }
            }
            $context = (new VkStoriesContextFactory)->getContext((int) $post->id);
            $template = $this->resolveTemplate($context);
            $image = (new VkStoriesGenerator)->generate($context, $template);

            try {
                if ($task->publication->exclusive_run_id !== null && $task->external_started_at === null) {
                    $task->update(['external_started_at' => now()]);
                }
                $res = (new VkStoryPoster)->post(
                    $vkApi, $post->getFullId(), $image,
                    $task->publication->exclusive_run_id !== null
                        ? fn () => $vkApi->findStoryForPost((int) $vkUser->vk_user_id, $post->getFullId()) : null,
                );

                if (! VkApiService::isStorySaveSuccessful($res)) {
                    throw new VkStoryNotSavedException(
                        'ВКонтакте не сохранил историю (count = 0). Ответ загрузки: '
                        .json_encode($this->withoutSecrets($res), JSON_UNESCAPED_UNICODE)
                    );
                }
            } finally {
                $this->deleteStoryFile($image);
            }

            $savedStory = $res['items'][0] ?? null;
            $task->update(['status' => PublicationTaskStatus::SUCCESS, 'external_id' => isset($savedStory['owner_id'], $savedStory['id']) ? $savedStory['owner_id'].'_'.$savedStory['id'] : null]);

            Log::channel('job')->info('История по офферу опубликована', [
                'publication_id' => $task->publication_id,
                'agent_id' => $agent->id,
                'exclusive_run_id' => $task->publication->exclusive_run_id,
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
                Log::channel('job')->warning('История опубликована, но не удалось продолжить цепочку задач', [
                    'task_id' => $this->taskId,
                ]);
            }
        } catch (StoryPublicationSkippedException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->warning($e->getMessage(), ['task_id' => $this->taskId]);
            Log::channel('job')->warning('Публикация истории пропущена', ['task_id' => $this->taskId]);
        } catch (VkStoryNotSavedException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), ['task_id' => $this->taskId]);
            Log::channel('job')->warning('Ошибка публикации истории по офферу', ['task_id' => $this->taskId]);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка публикации истории по офферу', ['task_id' => $this->taskId]);
        } catch (VKApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage() ?? '',
                'description' => $e->getDescription(),
                'vk_error' => $e->getError(),
            ]);
            Log::channel('job')->warning('Ошибка публикации истории по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Log::channel('job')->warning('Ошибка публикации истории по офферу', ['task_id' => $this->taskId]);
        }
    }

    private function deleteStoryFile(mixed $image): void
    {
        if (! is_string($image) || $image === '' || ! is_file($image)) {
            return;
        }

        try {
            unlink($image);
        } catch (\Throwable) {
        }
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    private function withoutSecrets(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/token|secret/i', $key) === 1) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->withoutSecrets($value) : $value;
        }

        return $clean;
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
