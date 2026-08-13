<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\OfferCreatedEvent;
use App\Listeners\ProcessOfferListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OfferCreatedEvent::class => [
            ProcessOfferListener::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    public function boot(): void
    {
        parent::boot();
    }
}
