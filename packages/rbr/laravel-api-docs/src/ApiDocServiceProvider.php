<?php

namespace Rbr\LaravelApiDocs;

use Illuminate\Support\ServiceProvider;
use Rbr\LaravelApiDocs\Console\Commands\GenerateApiDocs;

class ApiDocServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-docs.php', 'api-docs');
    }

    public function boot(): void
    {
        // Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'api-docs');

        // Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateApiDocs::class,
            ]);

            // Publishable assets
            $this->publishes([
                __DIR__.'/../config/api-docs.php' => config_path('api-docs.php'),
            ], 'api-docs-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'api-docs-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/api-docs'),
            ], 'api-docs-views');

            $this->publishes([
                __DIR__.'/../resources/js' => resource_path('js/vendor/api-docs'),
            ], 'api-docs-assets');

            $this->publishes([
                __DIR__.'/../resources/css' => resource_path('css/vendor/api-docs'),
            ], 'api-docs-css');

            $this->publishes([
                __DIR__.'/../public/images' => public_path('images'),
            ], 'api-docs-images');

            $this->publishes([
                __DIR__.'/../public/build' => public_path('vendor/api-docs/build'),
            ], 'api-docs-assets-build');
        }
    }
}
