<?php

declare(strict_types=1);

namespace App\Helpers;

use Throwable;
use VK\Exceptions\VKApiException;
use VK\Exceptions\VKClientException;

final class VkApiErrorInspector
{
    public const FLOOD_MAX_ATTEMPTS = 3;

    public const FLOOD_RETRY_DELAY_SECONDS = 1;

    public const TIMEOUT_MAX_ATTEMPTS = 3;

    public static function isFlood(Throwable $e): bool
    {
        if ($e instanceof VKApiException && $e->getErrorCode() === 9) {
            return true;
        }

        return str_contains($e->getMessage(), 'Flood control');
    }

    public static function isTimeout(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'cURL error 28')
            || str_contains($message, 'Operation timed out')
            || str_contains($message, 'timed out');
    }

    /**
     * Код 1 с пустым error_msg: SDK не создаёт VKApiException и теряет код.
     * Сюда попадает уже разобранное исключение и сам TypeError SDK.
     */
    public static function isVkUnknownError(Throwable $e): bool
    {
        if ($e instanceof VKApiException && $e->getErrorCode() === 1) {
            return true;
        }

        return $e instanceof \TypeError
            && str_contains($e->getMessage(), 'error_message');
    }

    public static function isHttp405(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Invalid http status: 405')
            || str_contains($message, '405 Method Not Allowed')
            || str_contains($message, 'ответ 405')
            || ($e instanceof VKClientException && $e->getCode() === 405);
    }

    /**
     * Отказ, который не исчезнет при следующем запуске: приватность,
     * нет права писать, пользователь удалён или недоступен.
     */
    public static function isPermanentDirectMessageRefusal(Throwable $e): bool
    {
        if ($e instanceof VKApiException && in_array($e->getErrorCode(), [7, 15, 18, 900, 901, 902], true)) {
            return true;
        }

        $message = mb_strtolower($e->getMessage());
        $needles = [
            'privacy settings',
            "can't send messages",
            'cannot send messages',
            'permission to perform this action is denied',
            'no access to call this method',
            'user was deleted',
            'deleted or banned',
            'user is blocked',
            'messages are disabled',
        ];

        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isAgentMessagesPermissionDenied(Throwable $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'no access to call this method')
            || str_contains($message, 'permission to perform this action is denied')
            || ($e instanceof VKApiException && in_array($e->getErrorCode(), [7, 15], true));
    }
}
