<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Models\PublicationTask;
use App\Models\VkProduct;
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

class ArchiveVkProductJob implements ShouldQueue
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

            $products = VkProduct::where('offer_id', $offer->id)
                ->where('is_archived', false)
                ->get();

            if ($products->isEmpty()) {
                $task->update(['status' => PublicationTaskStatus::SUCCESS]);

                Log::channel('job')->info('Архивация товаров не требуется', [
                    'offer' => $offer->code,
                ]);

                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
                return;
            }

            $agent = $offer->agent;
            /** @var VkUser|null $vkUser */
            $vkUser = $agent?->vkUser;

            if (!$vkUser || $vkUser->getToken() === '') {
                throw new NotFoundException('Для агента не задан токен');
            }

            $vkApi->setToken($vkUser->getToken());

            $batchProducts = $products->map(fn ($product) => [
                'group_id' => (int) $product->group_id,
                'product_id' => (int) $product->product_id,
            ])->all();

            $chunks = array_chunk($batchProducts, 25);

            foreach ($chunks as $index => $chunk) {
                try {
                    $vkApi->archiveProductsBatch($chunk);
                } catch (\Throwable $th) {
                    Log::channel('vk')->error('Ошибка архивации товаров в чанке', [
                        'chunk_index' => $index,
                        'offer_id' => $offer->id,
                        'error' => $th->getMessage(),
                    ]);
                }

                if ($index < count($chunks) - 1) {
                    usleep(350_000);
                }
            }

            VkProduct::where('offer_id', $offer->id)
                ->where('is_archived', false)
                ->update(['is_archived' => true]);

            $task->update(['status' => PublicationTaskStatus::SUCCESS]);

            Log::channel('job')->info('Товары по офферу архивированы', [
                'offer' => $offer->code,
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка архивации товаров по офферу', ['task_id' => $this->taskId]);
        } catch (VkApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'description' => $e->getDescription(),
                'vk_error' => $e->getError()
            ]);
            Log::channel('job')->warning('Ошибка архивации товаров по офферу', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Log::channel('job')->warning('Ошибка архивации товаров по офферу', ['task_id' => $this->taskId]);
        }
    }
}
