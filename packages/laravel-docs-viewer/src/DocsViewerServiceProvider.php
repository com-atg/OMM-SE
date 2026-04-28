<?php

namespace ComAtg\DocsViewer;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DocsViewerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/docs-viewer.php', 'docs-viewer');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'docs-viewer');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/docs-viewer.php' => config_path('docs-viewer.php'),
            ], 'docs-viewer-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/docs-viewer'),
            ], 'docs-viewer-views');

            $this->publishes([
                __DIR__.'/../resources/css/docs-prose.css' => resource_path('css/docs-prose.css'),
            ], 'docs-viewer-css');
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware(config('docs-viewer.middleware'))
            ->prefix(config('docs-viewer.route_prefix'))
            ->name(config('docs-viewer.route_name_prefix').'.')
            ->group(function () {
                Route::get('/', [DocumentationController::class, 'index'])->name('index');
                Route::get('/{slug}', [DocumentationController::class, 'show'])->name('show');
            });
    }
}
