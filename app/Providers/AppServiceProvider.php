<?php

namespace App\Providers;

use App\Logging\OperationContext;
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
        $this->app->singleton(OperationContext::class);
        $this->app->make(OperationContext::class)->registerJobBoundary();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(OperationContext::class)->registerRuntimeListeners();

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
