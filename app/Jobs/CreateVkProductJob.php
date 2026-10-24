<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\City;
use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Helpers\VkApiErrorInspector;
use App\Jobs\Concerns\DefersVkFloodControlledTask;
use App\Jobs\Concerns\MarksPublicationTaskFailedOnJobFailure;
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
    use DefersVkFloodControlledTask;
    use Dispatchable;
    use InteractsWithQueue;
    use MarksPublicationTaskFailedOnJobFailure;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        private readonly int $taskId,
        private readonly int $floodAttempt = 0,
    ) {}

    public function handle(VkApiService $vkApi,
        PublicationTaskDependencyResolver $taskDependencyResolver,
        TaskDispatcher $taskDispatcher): void
    {
        $task = PublicationTask::findOrFail($this->taskId);

        if (! $this->shouldHandleTask($task)) {
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

            $acceptingCityValues = array_map(
                fn (City $c) => $c->value,
                $offerCity->acceptedGroupCities()
            );

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

            $context = (new VkPostContextFactory)->getContext($offer->id);

            $name = "{$context->getCategory()}, {$context->address}";
            $description = "Подробности по телефону: {$context->getAgentPhone()}. Агент: {$context->agentName}";
            $price = $context->price;
            $categoryId = (int) config('vk.market_category_id', 1);

            $vkGroupIds = $vkApi->getGroupsWithMarketForUser((int) $vkUser->vk_user_id);
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

            $failedGroups = [];
            $failureMessages = [];

            foreach ($groupIds as $groupId) {
                $groupId = (int) $groupId;

                try {
                    $alreadyCreated = VkProduct::query()
                        ->where('offer_id', $offer->id)
                        ->where('group_id', $groupId)
                        ->where('is_archived', false)
                        ->exists();
                    if ($alreadyCreated) {
                        continue;
                    }

                    $imageUrls = $offer->images->pluck('original_url')->toArray();
                    $photoIds = $vkApi->uploadMarketPhoto($groupId, $imageUrls);
                    if (! $this->hasMainPhoto($photoIds)) {
                        $failedGroups[] = $groupId;
                        $failureMessages[] = 'Нет главного фото товара';
                        Log::channel('vk')->warning('Товар не создан: нет главного фото', [
                            'group_id' => $groupId,
                            'offer_id' => $offer->id,
                        ]);

                        continue;
                    }

                    $results = $vkApi->createProductsBatch(
                        $groupId,
                        $name,
                        $description,
                        $price,
                        $categoryId,
                        $photoIds
                    );

                    $productId = $this->productIdFromResult($results);
                    if ($productId === null) {
                        $failedGroups[] = $groupId;
                        $failureMessages[] = 'ВКонтакте не вернул товар';
                        Log::channel('vk')->warning('Не удалось создать товар в группе', [
                            'group_id' => $groupId,
                            'offer_id' => $offer->id,
                        ]);

                        continue;
                    }

                    VkProduct::create([
                        'offer_id' => $offer->id,
                        'agent_id' => $agent->id,
                        'group_id' => $groupId,
                        'product_id' => $productId,
                        'task_id' => $this->taskId,
                    ]);
                } catch (\Throwable $th) {
                    if (VkApiErrorInspector::isFlood($th)) {
                        throw $th;
                    }

                    $failedGroups[] = $groupId;
                    $failureMessages[] = $th->getMessage();
                    Log::channel('vk')->error('Ошибка создания товаров в группе '.$groupId, [
                        'offer_id' => $offer->id,
                        'error' => $th->getMessage(),
                    ]);
                }
            }

            if ($failedGroups !== []) {
                $task->update([
                    'status' => PublicationTaskStatus::FAILED,
                    'error' => $this->productFailureMessage($failureMessages),
                ]);
                Log::channel('job')->warning('Ошибка создания товаров по офферу', [
                    'task_id' => $this->taskId,
                    'failed_groups' => $failedGroups,
                ]);

                return;
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
        } catch (VKApiException $e) {
            if ($this->deferOnFlood($task, $e)) {
                return;
            }

            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage() ?? '',
                'description' => $e->getDescription(),
                'vk_error' => $e->getError(),
            ]);
            Log::channel('job')->warning('Ошибка создания товаров по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            if ($this->deferOnFlood($task, $e)) {
                return;
            }

            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Log::channel('job')->warning('Ошибка создания товаров по офферу', ['task_id' => $this->taskId]);
        }
    }

    /**
     * @param  list<mixed>  $photoIds
     */
    private function hasMainPhoto(array $photoIds): bool
    {
        $mainPhotoId = $photoIds[0] ?? null;

        return is_numeric($mainPhotoId) && (int) $mainPhotoId > 0;
    }

    private function productIdFromResult(mixed $results): ?int
    {
        if (is_numeric($results) && (int) $results > 0) {
            return (int) $results;
        }

        if (! is_array($results)) {
            return null;
        }

        if (isset($results['market_item_id']) && is_numeric($results['market_item_id']) && (int) $results['market_item_id'] > 0) {
            return (int) $results['market_item_id'];
        }

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $response = $result['response'] ?? null;
            if (is_array($response) && isset($response['market_item_id']) && is_numeric($response['market_item_id'])) {
                $productId = (int) $response['market_item_id'];

                return $productId > 0 ? $productId : null;
            }

            if (is_numeric($response) && (int) $response > 0) {
                return (int) $response;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $failureMessages
     */
    private function productFailureMessage(array $failureMessages): string
    {
        $unique = array_values(array_unique(array_filter($failureMessages, static fn (string $message): bool => $message !== '')));
        if ($unique === []) {
            return 'Не удалось создать товар. Задачу можно повторить.';
        }

        $message = implode('; ', $unique);
        if (! str_contains($message, 'повторить')) {
            $message .= ' Задачу можно повторить.';
        }

        return $message;
    }
}
