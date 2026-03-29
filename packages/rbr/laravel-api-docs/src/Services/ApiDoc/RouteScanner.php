<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

use Illuminate\Support\Facades\Route;

class RouteScanner
{
    public function scan(): array
    {
        $excludePrefixes = config('api-docs.exclude_prefixes', []);
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if ($this->shouldExclude($uri, $excludePrefixes)) {
                continue;
            }

            $action = $route->getAction();
            $controllerClass = null;
            $controllerMethod = null;
            $isClosure = true;

            if (isset($action['controller'])) {
                $isClosure = false;
                [$controllerClass, $controllerMethod] = explode('@', $action['controller']);
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $routes[] = [
                    'http_method' => $method,
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'controller_class' => $controllerClass,
                    'controller_method' => $controllerMethod,
                    'middleware' => $route->gatherMiddleware(),
                    'is_closure' => $isClosure,
                    'prefix' => $this->extractPrefix($uri),
                ];
            }
        }

        return $routes;
    }

    protected function shouldExclude(string $uri, array $excludePrefixes): bool
    {
        foreach ($excludePrefixes as $prefix) {
            if (str_starts_with($uri, $prefix)) {
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
