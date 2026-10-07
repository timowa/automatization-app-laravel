<?php

namespace App\Providers;

use App\Services\ChangelogService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ChangelogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layout', function ($view): void {
            $changelog = app(ChangelogService::class);

            $view->with([
                'productVersion' => $changelog->currentLabel(),
                'recentReleases' => $changelog->latest(4),
                'showVersionNewBadge' => $changelog->shouldShowNewBadge(),
            ]);
        });
    }
}

