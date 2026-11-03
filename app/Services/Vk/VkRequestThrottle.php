<?php

declare(strict_types=1);

namespace App\Services\Vk;

use Illuminate\Support\Facades\Cache;

final class VkRequestThrottle
{
    public function wait(): void
    {
        Cache::lock('vk-request-throttle', 5)->block(10, function (): void {
            $last = (float) Cache::get('vk-request-last-start', 0);
            $delay = max(0, 0.35 - (microtime(true) - $last));
            if ($delay > 0) {
                usleep((int) ceil($delay * 1_000_000));
            }
            Cache::put('vk-request-last-start', microtime(true), 10);
        });
    }
}
