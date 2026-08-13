<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Models\PublicationTask;
use App\Models\VkProduct;
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

class EditVkProductJob implements ShouldQueue
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
            $vkUser = $agent?->vkUser;

            if (!$vkUser || $vkUser->getToken() === '') {
                throw new NotFoundException('Для агента не задан токен');
            }

            $vkApi->setToken($vkUser->getToken());

            $context = (new VkPostContextFactory())->getContext($offer->id);

            $name = "{$context->getCategory()}, {$context->address}";
            $description = "Подробности по телефону: {$context->agentPhone}\nАгент: {$context->agentName}";
            $price = $context->price;
            $categoryId = config('vk.market_category_id', 1);

            $products = VkProduct::where('offer_id', $offer->id)
                ->where('is_archived', false)
                ->get();

            if ($products->isEmpty()) {
                throw new NotFoundException('Товары не найдены');
            }

            foreach ($products as $product) {
                $vkApi->editProduct(
                    (int) $product->group_id,
                    (int) $product->product_id,
                    $name,
                    $description,
                    $price,
                    (int) $categoryId
                );

                usleep(350_000);
            }

            $task->update(['status' => PublicationTaskStatus::SUCCESS]);

            Log::channel('job')->info('Товары по офферу отредактированы', [
                'offer' => $offer->code,
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка редактирования товаров по офферу', ['task_id' => $this->taskId]);
        } catch (VkApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'description' => $e->getDescription(),
                'vk_error' => $e->getError()
            ]);
            Log::channel('job')->warning('Ошибка редактирования товаров по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Log::channel('job')->warning('Ошибка редактирования товаров по офферу', ['task_id' => $this->taskId]);
        }
    }
}
