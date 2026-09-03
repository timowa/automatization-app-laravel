<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\City;
use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Models\PublicationTask;
use App\Models\VkGroup;
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

class CreateVkRepostJob implements ShouldQueue
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

        if ($task->status !== PublicationTaskStatus::QUEUED) {
            return;
        }

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
                throw new NotFoundException('Оффер для поста не найден');
            }

            $offerCity = $offer->city();
            if ($offerCity === null) {
                $task->update(['status' => PublicationTaskStatus::SUCCESS]);
                Log::channel('job')->info('Репост не выполнен: у оффера не указан город', [
                    'offer' => $offer->code,
                ]);
                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
                return;
            }

            // Найти city групп, которые принимают город оффера
            $acceptingCityValues = array_filter(
                City::cases(),
                fn (City $c) => in_array($offerCity, $c->acceptedCities(), true)
            );
            $acceptingCityValues = array_map(fn (City $c) => $c->value, $acceptingCityValues);

            $groups = VkGroup::whereIn('city', $acceptingCityValues)->get();
            if ($groups->isEmpty()) {
                $task->update(['status' => PublicationTaskStatus::SUCCESS]);
                Log::channel('job')->info('Репост не выполнен: нет групп для города', [
                    'offer' => $offer->code,
                    'city' => $offerCity->label(),
                ]);
                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
                return;
            }

            $agent = $offer->agent;
            /** @var VkUser|null $vkUser */
            $vkUser = $agent?->vkUser;
            if (!$vkUser || !$vkUser->isTokenValid()) {
                throw new NotFoundException('Для агента не задан токен');
            }

            $groupIds = $groups->pluck('group_id')->toArray();

            $vkApi->setToken($vkUser->getToken());
            $result = $vkApi->createReposts((int) $vkUser->vk_user_id, $post->getFullId(), $groupIds);

            $successGroupIds = [];

            foreach ($result as $res) {
                $groupId = $res['group_id'] ?? null;
                $response = $res['response'] ?? null;

                if (is_array($response) && isset($response['success']) && $response['success'] === 1) {
                    $successGroupIds[] = $groupId;
                    continue;
                }

                Log::channel('vkRepost')->warning('Репост не выполнен', [
                    'post_id' => $postId,
                    'group_id' => $groupId,
                    'response' => $response,
                ]);
            }

            $task->update(['status' => PublicationTaskStatus::SUCCESS]);

            Log::channel('job')->info('Репосты по офферу выполнены', [
                'offer' => $offer->code,
                'post_id' => $post->id,
                'group_ids' => $successGroupIds
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка репоста по офферу', ['task_id' => $this->taskId]);
        } catch (VkApiException $e) {
            if ($e->getErrorCode() === 5 && isset($vkUser)) {
                $vkUser->update(['is_token_valid' => false]);
            }
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'description' => $e->getDescription(),
                'vk_error' => $e->getError()
            ]);
            Log::channel('job')->warning('Ошибка репоста по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Log::channel('job')->warning('Ошибка репоста по офферу', ['task_id' => $this->taskId]);
        }
    }
}
