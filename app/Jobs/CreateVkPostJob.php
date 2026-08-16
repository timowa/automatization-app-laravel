<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Deal;
use App\Enums\PublicationTaskStatus;
use App\Events\VkWallPostCreatedEvent;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Helpers\ScenarioVkPostTemplateResolver;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\PublicationTask;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Scenarios\TaskDispatcher;
use App\Services\Vk\VkApiService;
use App\Services\Vk\WallPost\VkPostContextFactory;
use App\Services\Vk\WallPost\VkPostGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use VK\Exceptions\VKApiException;

class CreateVkPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly int $taskId,
    )
    {
    }

    public function handle(VkApiService $vkApi,
                           ScenarioVkPostTemplateResolver $templateResolver,
                           PublicationTaskDependencyResolver $taskDependencyResolver,
                           TaskDispatcher $taskDispatcher): void
    {
        $task = PublicationTask::findOrFail($this->taskId);

        if ($task->status !== PublicationTaskStatus::QUEUED) {
            return;
        }

        try {
            $task->update(['status' => PublicationTaskStatus::PROCESSING]);

            $task->load('publication.offer');
            $offer = $task->publication->offer;
            if (!$offer) {
                throw new NotFoundException('Оффер не найден');
            }

            /** @var Agent $agent */
            $agent = $offer->agent;
            /** @var VkUser|null $vkUser */
            $vkUser = $agent->vkUser;

            if (!$agent || !$vkUser) {
                throw new NotFoundException('Не найдены данные агента');
            }

            if ($vkUser->getToken() === '') {
                throw new NotFoundException('Для агента не задан токен');
            }

            $vkApi->setToken($vkUser->getToken());

            $context = (new VkPostContextFactory())->getContext($offer->id);
            $template = $templateResolver->resolve($task->publication->scenario);
            $message = (new VkPostGenerator())->generate($context, $template);

            $res = $vkApi->wallPost((int) $vkUser->vk_user_id, $message, $context->images);

            $post = VkWallPost::create([
                'offer_id' => $offer->id,
                'post_id' => $res['post_id'],
                'owner_id' => (int) $vkUser->vk_user_id,
                'task_id' => (int) $this->taskId,
            ]);

            $task->update(['status' => PublicationTaskStatus::SUCCESS, 'external_id' => $post->id]);

            Log::channel('job')->info('Пост по офферу опубликован', [
                'offer' => $offer->code,
                'post_id' => $post->id,
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка создания поста по офферу', ['task_id' => $this->taskId]);
            Log::channel('vk')->warning($e->getMessage());
        } catch (VkApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'description' => $e->getDescription(),
                'vk_error' => $e->getError()
            ]);
            Log::channel('job')->warning('Ошибка создания поста по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Log::channel('job')->warning('Ошибка создания поста по офферу', ['task_id' => $this->taskId]);
        }
    }
}
