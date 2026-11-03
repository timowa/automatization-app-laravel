<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PublicationCompletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CheckPublicationCompletionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $publicationId) {}

    public function handle(PublicationCompletionService $service): void
    {
        $service->check($this->publicationId);
    }
}
