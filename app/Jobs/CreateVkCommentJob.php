<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Models\Agent;
use App\Models\PublicationTask;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Scenarios\TaskDispatcher;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use VK\Exceptions\VKApiException;

class CreateVkCommentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly int $taskId
    )
    {
    }

    public function handle(VkApiService $vkApi,
                           PublicationTaskDependencyResolver $taskDependencyResolver,
                           TaskDispatcher $taskDispatcher): void
    {
        $task = PublicationTask::findOrFail($this->taskId);

        try {
            $task->update(['status' => PublicationTaskStatus::PROCESSING]);

            $parentTask = $task->parentTask;
            $postId = $parentTask->external_id;
            $post = VkWallPost::find($postId);

            if (!$post) {
                throw new NotFoundException('Пост не найден');
            }

            $offer = $post->offer;
            if (!$offer) {
                throw new NotFoundException('Оффер не найден');
            }

            $agent = $offer->agent;
            /** @var VkUser|null $vkUser */
            $vkUser = $agent?->vkUser;

            if (!$vkUser || $vkUser->getToken() === '') {
                throw new NotFoundException('Для агента не задан токен');
            }

            $vkApi->setToken($vkUser->getToken());

            $message = "Подробности по объекту уточняйте у агента в личных сообщениях 📩";

            $result = $vkApi->createComment((int) $post->owner_id, (int) $post->post_id, $message);

            $task->update(['status' => PublicationTaskStatus::SUCCESS, 'external_id' => (string) $result['comment_id']]);

            Log::channel('job')->info('Комментарий к посту по офферу создан', [
                'offer' => $offer->code,
                'post_id' => $post->id,
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка создания комментария по офферу', ['task_id' => $this->taskId]);
        } catch (VkApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'description' => $e->getDescription(),
                'vk_error' => $e->getError()
            ]);
            Log::channel('job')->warning('Ошибка создания комментария по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Log::channel('job')->warning('Ошибка создания комментария по офферу', ['task_id' => $this->taskId]);
        }
    }
}
