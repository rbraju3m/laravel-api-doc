<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

use Symfony\Component\Process\Process;

class ExternalProjectScanner
{
    public const DEFAULT_EXCLUDE_PREFIXES = [
        '_debugbar',
        '_boost',
        '_ignition',
        'sanctum',
        'telescope',
        'horizon',
    ];

    /**
     * Scan routes from an external Laravel project using artisan route:list.
     */
    public function scan(string $projectPath, array $excludePrefixes = []): array
    {
        $process = new Process(
            ['php', 'artisan', 'route:list', '--json'],
            $projectPath,
        );
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'Failed to scan routes: '.$process->getErrorOutput()
            );
        }

        $routeList = json_decode($process->getOutput(), true);

        if (! is_array($routeList)) {
            throw new \RuntimeException('Invalid JSON from route:list command.');
        }

        $allExcluded = array_merge(self::DEFAULT_EXCLUDE_PREFIXES, $excludePrefixes);

        $routes = [];

        foreach ($routeList as $route) {
            $method = strtoupper($route['method'] ?? 'GET');

            // route:list may return "GET|HEAD" combined methods
            $methods = explode('|', $method);

            foreach ($methods as $m) {
                $m = trim($m);
                if ($m === 'HEAD') {
                    continue;
                }

                $uri = ltrim($route['uri'] ?? '/', '/');

                // Filter out excluded prefixes
                if ($this->isExcluded($uri, $allExcluded)) {
                    continue;
                }

                $controllerClass = null;
                $controllerMethod = null;
                $isClosure = true;

                $action = $route['action'] ?? '';
                if (str_contains($action, '@')) {
                    $isClosure = false;
                    [$controllerClass, $controllerMethod] = explode('@', $action, 2);
                }

                $middleware = $route['middleware'] ?? [];
                if (is_string($middleware)) {
                    $middleware = $middleware ? explode("\n", $middleware) : [];
                }

                $routes[] = [
                    'http_method' => $m,
                    'uri' => $uri,
                    'name' => $route['name'] ?? null,
                    'controller_class' => $controllerClass,
                    'controller_method' => $controllerMethod,
                    'middleware' => $middleware,
                    'is_closure' => $isClosure,
                    'prefix' => $this->extractPrefix($uri),
                ];
            }
        }

        return $routes;
    }

    /**
     * Check if a URI should be excluded based on prefix matching.
     */
    protected function isExcluded(string $uri, array $excludePrefixes): bool
    {
        foreach ($excludePrefixes as $prefix) {
            $prefix = ltrim($prefix, '/');
            if ($prefix && str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function extractPrefix(string $uri): string
    {
        $segments = explode('/', trim($uri, '/'));

        return $segments[0] ?? '/';
    }
}
