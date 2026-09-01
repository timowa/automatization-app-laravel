<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PublicationTaskStatus;
use App\Exceptions\NotFoundException;
use App\Helpers\PublicationTaskDependencyResolver;
use App\Models\Agent;
use App\Models\OfferImage;
use App\Models\PublicationTask;
use App\Models\VkUser;
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

class UploadVkImagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    private const MAX_ATTEMPTS_PER_IMAGE = 3;
    private const RETRY_DELAY_MICROSECONDS = 500000;

    public function __construct(
        private readonly int $taskId,
    ) {
    }

    public function handle(
        VkApiService $vkApi,
        PublicationTaskDependencyResolver $taskDependencyResolver,
        TaskDispatcher $taskDispatcher,
    ): void {
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
            $imageCaption = $context->getImageCaption();

            $pendingImages = OfferImage::where('offer_id', $offer->id)
                ->whereNull('media_id')
                ->orderBy('sort_order')
                ->get();

            if ($pendingImages->isEmpty()) {
                $task->update(['status' => PublicationTaskStatus::SUCCESS]);
                Log::channel('job')->info('Изображения оффера уже загружены', [
                    'offer' => $offer->code,
                ]);
                $taskDependencyResolver->release($this->taskId);
                $taskDispatcher->dispatch($task->publication_id);
                return;
            }

            $attempts = [];

            foreach ($pendingImages as $image) {
                $attempts[$image->id] = 0;
            }

            $allUploaded = false;

            while (!$allUploaded) {
                $allUploaded = true;

                foreach ($pendingImages as $image) {
                    if ($image->media_id !== null) {
                        continue;
                    }

                    if ($attempts[$image->id] >= self::MAX_ATTEMPTS_PER_IMAGE) {
                        continue;
                    }

                    $attempts[$image->id]++;
                    Log::channel('job')->info('Загрузка изображения', [
                        'offer' => $offer->code,
                        'image_id' => $image->id,
                        'attempt' => $attempts[$image->id],
                    ]);

                    $mediaId = $vkApi->uploadWallPhoto(
                        $image->original_url,
                        (int) $vkUser->vk_user_id,
                        $imageCaption,
                    );

                    if ($mediaId !== null) {
                        $image->update(['media_id' => $mediaId]);
                        Log::channel('job')->info('Изображение загружено', [
                            'offer' => $offer->code,
                            'image_id' => $image->id,
                            'media_id' => $mediaId,
                        ]);
                    } else {
                        $allUploaded = false;
                        usleep(self::RETRY_DELAY_MICROSECONDS);
                    }
                }

                if (!$allUploaded) {
                    $stillPending = array_filter($attempts, fn (int $a) => $a < self::MAX_ATTEMPTS_PER_IMAGE);
                    if (empty($stillPending)) {
                        break;
                    }
                }
            }

            $failedImages = OfferImage::where('offer_id', $offer->id)
                ->whereNull('media_id')
                ->count();

            if ($failedImages > 0) {
                $task->update([
                    'status' => PublicationTaskStatus::FAILED,
                    'error' => "Не удалось загрузить {$failedImages} изображений из {$pendingImages->count()}",
                ]);
                Log::channel('job')->warning('Ошибка загрузки изображений оффера', [
                    'task_id' => $this->taskId,
                    'failed_count' => $failedImages,
                ]);
                return;
            }

            $task->update(['status' => PublicationTaskStatus::SUCCESS]);
            Log::channel('job')->info('Изображения оффера загружены', [
                'offer' => $offer->code,
                'count' => $pendingImages->count(),
            ]);

            $taskDependencyResolver->release($this->taskId);
            $taskDispatcher->dispatch($task->publication_id);
        } catch (NotFoundException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('job')->warning('Ошибка загрузки изображений оффера', ['task_id' => $this->taskId]);
            Log::channel('vk')->warning($e->getMessage());
        } catch (VKApiException $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'description' => $e->getDescription(),
                'vk_error' => $e->getError(),
            ]);
            Log::channel('job')->warning('Ошибка загрузки изображений оффера', ['task_id' => $this->taskId]);
        } catch (\Throwable $e) {
            $task->update(['status' => PublicationTaskStatus::FAILED, 'error' => $e->getMessage()]);
            Log::channel('vk')->error($e->getMessage(), [
                'task_id' => $this->taskId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Log::channel('job')->warning('Ошибка загрузки изображений оффера', ['task_id' => $this->taskId]);
        }
    }
}