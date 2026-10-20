<?php

declare(strict_types=1);

namespace App\Helpers;

use Throwable;
use VK\Exceptions\VKApiException;

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
}
