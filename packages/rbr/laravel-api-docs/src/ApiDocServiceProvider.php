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
        // Share config with Inertia
        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share([
                'apiDocsConfig' => [
                    'route_prefix' => config('api-docs.route_prefix', 'docs/api'),
                    'title' => config('api-docs.title', 'API Documentation'),
                ],
            ]);
        }

        // Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'api-docs');

        // Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Auto-publish built assets if not present in public directory
        // Runs in console context (artisan) where file permissions allow writing
        if ($this->app->runningInConsole()) {
            $this->autoPublishAssets();
        }

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

    /**
     * Auto-copy pre-built assets to public directory if they don't exist.
     * This ensures the package works immediately after composer install
     * without requiring manual vendor:publish.
     */
    /**
     * Auto-copy pre-built assets to public directory if they don't exist.
     * This ensures the package works immediately after composer install
     * without requiring manual vendor:publish.
     */
    protected function autoPublishAssets(): void
    {
        $targetManifest = public_path('vendor/api-docs/build/manifest.json');

        // Skip if assets already published
        if (file_exists($targetManifest)) {
            return;
        }

        $sourceDir = __DIR__.'/../public/build';
        $sourceManifest = $sourceDir.'/manifest.json';

        // Skip if package has no built assets
        if (!file_exists($sourceManifest)) {
            return;
        }

        $targetDir = public_path('vendor/api-docs/build');

        try {
            $this->copyDirectory($sourceDir, $targetDir);
        } catch (\Throwable $e) {
            // Silently fail — user can manually run:
            // php artisan vendor:publish --tag=api-docs-assets-build --force
        }
    }

    /**
     * Recursively copy a directory.
     */
    protected function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        if (!is_dir($target)) {
            return;
        }

        $items = new \DirectoryIterator($source);

        foreach ($items as $item) {
            if ($item->isDot()) {
                continue;
            }

            $sourcePath = $item->getPathname();
            $targetPath = $target.'/'.$item->getFilename();

            if ($item->isDir()) {
                $this->copyDirectory($sourcePath, $targetPath);
            } else {
                @copy($sourcePath, $targetPath);
            }
        }
    }
}
