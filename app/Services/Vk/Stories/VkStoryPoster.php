<?php

declare(strict_types=1);

namespace App\Services\Vk\Stories;

use App\Exceptions\VkStoryNotSavedException;
use App\Helpers\VkApiErrorInspector;
use App\Logging\SensitiveDataRedactor;
use App\Services\Vk\VkApiService;
use Illuminate\Support\Facades\Log;
use Throwable;
use VK\Exceptions\VKApiException;

final class VkStoryPoster
{
    public function __construct(
        private readonly int $pauseMicroseconds = 350_000,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function post(VkApiService $vkApi, string $postId, string $imagePath): array
    {
        $last = null;

        for ($attempt = 1; $attempt <= VkApiErrorInspector::TIMEOUT_MAX_ATTEMPTS; $attempt++) {
            try {
                return $vkApi->storiesPost($postId, $imagePath);
            } catch (Throwable $e) {
                $last = $e;
                if (! $this->isRetryable($e) || $attempt === VkApiErrorInspector::TIMEOUT_MAX_ATTEMPTS) {
                    $reason = $this->failureReason($e);
                    if ($reason !== null && $attempt === VkApiErrorInspector::TIMEOUT_MAX_ATTEMPTS) {
                        throw new VkStoryNotSavedException($reason, previous: $e);
                    }

                    throw $e;
                }

                Log::channel('vk')->warning('Публикация истории будет повторена', [
                    'attempt' => $attempt,
                    'error_code' => $e instanceof VKApiException ? $e->getErrorCode() : null,
                    'http_status' => VkApiErrorInspector::isHttp405($e) ? 405 : null,
                    'response' => (new SensitiveDataRedactor)->redactString($e->getMessage()),
                ]);

                if ($this->pauseMicroseconds > 0) {
                    usleep($this->pauseMicroseconds);
                }
            }
        }

        throw $last ?? new VkStoryNotSavedException('Не удалось опубликовать историю');
    }

    private function isRetryable(Throwable $e): bool
    {
        return VkApiErrorInspector::isVkUnknownError($e)
            || VkApiErrorInspector::isHttp405($e)
            || VkApiErrorInspector::isTimeout($e);
    }

    private function failureReason(Throwable $e): ?string
    {
        if (VkApiErrorInspector::isVkUnknownError($e)) {
            return 'ВКонтакте вернул пустую ошибку (код 1). Попытки исчерпаны.';
        }

        if (VkApiErrorInspector::isHttp405($e)) {
            return 'ВКонтакте не принял загрузку фото истории (ответ 405). Попытки исчерпаны.';
        }

        return null;
    }
}
