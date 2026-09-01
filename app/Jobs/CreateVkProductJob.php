<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\City;
use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Models\Agent;
use App\Models\PublicationTask;
use App\Models\VkGroup;
use App\Models\VkProduct;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Scenarios\TaskDispatcher;
use App\Services\Vk\VkApiService;
use App\Services\Vk\WallPost\VkPostContextFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use VK\Exceptions\VKApiException;

class CreateVkProductJob implements ShouldQueue
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
                throw new NotFoundException('Оффер не найден');
            }
            $agent = $offer->agent;
            /** @var VkUser|null $vkUser */
            $vkUser = $agent?->vkUser;

            if (!$vkUser || $vkUser->getToken() === '') {
                throw new NotFoundException('Для агента не задан токен');
            }

            $offerCity = $offer->city();
            if ($offerCity === null) {
                $task->update(['status' => PublicationTaskStatus::SUCCESS]);
                Log::channel('job')->info('Товары не созданы: у оффера не указан город', [
                    'offer' => $offer->code,
                ]);
                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
                return;
            }

            $acceptingCityValues = array_filter(
                City::cases(),
                fn (City $c) => in_array($offerCity, $c->acceptedCities(), true)
            );
            $acceptingCityValues = array_map(fn (City $c) => $c->value, $acceptingCityValues);

            $cityGroupIds = VkGroup::whereIn('city', $acceptingCityValues)->pluck('group_id')->toArray();
            if (empty($cityGroupIds)) {
                $task->update(['status' => PublicationTaskStatus::SUCCESS]);
                Log::channel('job')->info('Товары не созданы: нет групп для города', [
                    'offer' => $offer->code,
                    'city' => $offerCity->label(),
                ]);
                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
                return;
            }

            $vkApi->setToken($vkUser->getToken());

            $context = (new VkPostContextFactory())->getContext($offer->id);

            $name = "{$context->getCategory()}, {$context->address}";
            $description = "Подробности по телефону: {$context->getAgentPhone()}. Агент: {$context->agentName}";
            $price = $context->price;
            $categoryId = (int) config('vk.market_category_id', 1);

            $vkGroupIds = $vkApi->getGroupsWithMarketForUser((int)$vkUser->vk_user_id);
            $groupIds = array_values(array_intersect($cityGroupIds, $vkGroupIds));

            if (empty($groupIds)) {
                $task->update(['status' => PublicationTaskStatus::SUCCESS]);
                Log::channel('job')->info('Товары не созданы: нет доступных групп с market для города', [
                    'offer' => $offer->code,
                    'city' => $offerCity->label(),
                ]);
                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
                return;
            }

            foreach ($groupIds as $groupId) {
                $imageUrls = $offer->images->pluck('original_url')->toArray();
                $photoIds = $vkApi->uploadMarketPhoto((int)$groupId, $imageUrls);
                try {
                    $results = $vkApi->createProductsBatch(
                        (int)$groupId,
                        $name,
                        $description,
                        $price,
                        $categoryId,
                        $photoIds
                    );

                    foreach ($results as $result) {
                        $groupId = $result['group_id'] ?? null;
                        $response = $result['response'] ?? null;

                        if ($groupId === null || $response === null) {
                            continue;
                        }

                        $productId = is_array($response)
                            ? ($response['market_item_id'] ?? null)
                            : (int) $response;

                        if ($productId === null || $productId === 0) {
                            Log::channel('vk')->warning('Не удалось создать товар в группе', [
                                'group_id' => $groupId,
                                'offer_id' => $offer->id,
                                'response' => $response,
                                'result' => $result,
                            ]);
                            continue;
                        }

                        VkProduct::create([
                            'offer_id' => $offer->id,
                            'agent_id' => $agent->id,
                            'group_id' => (int) $groupId,
                            'product_id' => (int) $productId,
                            'task_id' => $this->taskId,
                        ]);
                    }
                } catch (\Throwable $th) {
                    Log::channel('vk')->error('Ошибка создания товаров в группе ' . $groupId, [
                        'offer_id' => $offer->id,
                        'error' => $th->getMessage(),
                    ]);
                    continue;
                }
            }

            $task->update(['status' => PublicationTaskStatus::SUCCESS]);

            Log::channel('job')->info('Товары по офферу созданы', [
                'offer' => $offer->code,
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка создания товаров по офферу', ['task_id' => $this->taskId]);
        } catch (VkApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'description' => $e->getDescription(),
                'vk_error' => $e->getError()
            ]);
            Log::channel('job')->warning('Ошибка создания товаров по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Log::channel('job')->warning('Ошибка создания товаров по офферу', ['task_id' => $this->taskId]);
        }
    }
}
