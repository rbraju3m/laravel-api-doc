<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

use Rbr\LaravelApiDocs\Models\ApiEndpoint;
use Rbr\LaravelApiDocs\Models\ApiEndpointGroup;
use Rbr\LaravelApiDocs\Models\ApiEndpointParameter;
use Rbr\LaravelApiDocs\Models\ApiEndpointResponse;
use Rbr\LaravelApiDocs\Models\ApiProject;
use Illuminate\Support\Facades\DB;

class DocGenerator
{
    public function __construct(
        protected RouteScanner $routeScanner,
        protected ControllerParser $controllerParser,
        protected RequestValidationParser $validationParser,
        protected ResponseGenerator $responseGenerator,
        protected ExternalProjectScanner $externalScanner,
        protected ExternalControllerParser $externalControllerParser,
        protected ExternalRequestParser $externalRequestParser,
        protected ExternalResponseAnalyzer $externalResponseAnalyzer,
        protected ExternalResourceParser $externalResourceParser,
    ) {}

    public function generate(): array
    {
        $routes = $this->routeScanner->scan();
        $stats = ['groups' => 0, 'endpoints' => 0];

        DB::transaction(function () use ($routes, &$stats) {
            // Delete only local (non-external) groups and their cascaded data
            $localGroupIds = ApiEndpointGroup::whereNull('api_project_id')->pluck('id');
            ApiEndpointResponse::whereIn('api_endpoint_id',
                ApiEndpoint::whereIn('api_endpoint_group_id', $localGroupIds)->pluck('id')
            )->delete();
            ApiEndpointParameter::whereIn('api_endpoint_id',
                ApiEndpoint::whereIn('api_endpoint_group_id', $localGroupIds)->pluck('id')
            )->delete();
            ApiEndpoint::whereIn('api_endpoint_group_id', $localGroupIds)->delete();
            ApiEndpointGroup::whereNull('api_project_id')->delete();

            // Group routes by prefix
            $grouped = [];
            foreach ($routes as $route) {
                $prefix = $route['prefix'];
                $grouped[$prefix][] = $route;
            }

            $sortOrder = 0;

            foreach ($grouped as $prefix => $groupRoutes) {
                $group = ApiEndpointGroup::create([
                    'name' => ucfirst($prefix === '/' ? 'Root' : str_replace('-', ' ', $prefix)),
                    'prefix' => $prefix,
                    'sort_order' => $sortOrder++,
                ]);

                $stats['groups']++;

                foreach ($groupRoutes as $route) {
                    $controllerInfo = ['description' => null, 'form_request_class' => null, 'uri_parameters' => []];

                    if ($route['controller_class'] && $route['controller_method']) {
                        $controllerInfo = $this->controllerParser->parse(
                            $route['controller_class'],
                            $route['controller_method'],
                        );
                    }

                    $middleware = $route['middleware'] ?? [];
                    $isAuthenticated = $this->hasAuthMiddleware($middleware);

                    $endpoint = ApiEndpoint::create([
                        'api_endpoint_group_id' => $group->id,
                        'http_method' => $route['http_method'],
                        'uri' => $route['uri'],
                        'name' => $route['name'],
                        'controller_class' => $route['controller_class'],
                        'controller_method' => $route['controller_method'],
                        'description' => $controllerInfo['description'],
                        'middleware' => $middleware,
                        'is_authenticated' => $isAuthenticated,
                        'is_closure' => $route['is_closure'],
                    ]);

                    $stats['endpoints']++;

                    // URI parameters
                    foreach ($controllerInfo['uri_parameters'] as $uriParam) {
                        ApiEndpointParameter::create([
                            'api_endpoint_id' => $endpoint->id,
                            'name' => $uriParam['name'],
                            'location' => 'uri',
                            'type' => $uriParam['type'],
                            'required' => true,
                            'description' => 'URI parameter',
                            'example' => $uriParam['type'] === 'integer' ? '1' : 'value',
                        ]);
                    }

                    // FormRequest parameters
                    $hasValidation = false;
                    if ($controllerInfo['form_request_class']) {
                        $params = $this->validationParser->parse($controllerInfo['form_request_class']);
                        $hasValidation = ! empty($params);

                        foreach ($params as $param) {
                            ApiEndpointParameter::create([
                                'api_endpoint_id' => $endpoint->id,
                                ...$param,
                            ]);
                        }
                    }

                    // Responses
                    $responses = $this->responseGenerator->generate(
                        $route['http_method'],
                        $hasValidation,
                        $isAuthenticated,
                    );

                    foreach ($responses as $response) {
                        ApiEndpointResponse::create([
                            'api_endpoint_id' => $endpoint->id,
                            ...$response,
                        ]);
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * Generate documentation for an external Laravel project.
     */
    public function generateForProject(ApiProject $project): array
    {
        $routes = $this->externalScanner->scan(
            $project->project_path,
            $project->exclude_prefixes ?? [],
        );
        $stats = ['groups' => 0, 'endpoints' => 0];

        DB::transaction(function () use ($routes, &$stats, $project) {
            // Delete existing groups for this project
            $groupIds = ApiEndpointGroup::where('api_project_id', $project->id)->pluck('id');
            ApiEndpointResponse::whereIn('api_endpoint_id',
                ApiEndpoint::whereIn('api_endpoint_group_id', $groupIds)->pluck('id')
            )->delete();
            ApiEndpointParameter::whereIn('api_endpoint_id',
                ApiEndpoint::whereIn('api_endpoint_group_id', $groupIds)->pluck('id')
            )->delete();
            ApiEndpoint::whereIn('api_endpoint_group_id', $groupIds)->delete();
            ApiEndpointGroup::where('api_project_id', $project->id)->delete();

            // Group routes by prefix
            $grouped = [];
            foreach ($routes as $route) {
                $prefix = $route['prefix'];
                $grouped[$prefix][] = $route;
            }

            $sortOrder = 0;

            foreach ($grouped as $prefix => $groupRoutes) {
                $group = ApiEndpointGroup::create([
                    'api_project_id' => $project->id,
                    'name' => ucfirst($prefix === '/' ? 'Root' : str_replace('-', ' ', $prefix)),
                    'prefix' => $prefix,
                    'sort_order' => $sortOrder++,
                ]);

                $stats['groups']++;

                foreach ($groupRoutes as $route) {
                    $controllerInfo = ['description' => null, 'form_request_class' => null, 'inline_rules' => [], 'uri_parameters' => [], 'request_only_fields' => []];

                    if ($route['controller_class'] && $route['controller_method']) {
                        $controllerInfo = $this->externalControllerParser->parse(
                            $project->project_path,
                            $route['controller_class'],
                            $route['controller_method'],
                            $route['uri'],
                        );
                    }

                    $middleware = $route['middleware'] ?? [];
                    $isAuthenticated = $this->hasAuthMiddleware($middleware);

                    $endpoint = ApiEndpoint::create([
                        'api_endpoint_group_id' => $group->id,
                        'http_method' => $route['http_method'],
                        'uri' => $route['uri'],
                        'name' => $route['name'],
                        'controller_class' => $route['controller_class'],
                        'controller_method' => $route['controller_method'],
                        'description' => $controllerInfo['description'],
                        'middleware' => $middleware,
                        'is_authenticated' => $isAuthenticated,
                        'is_closure' => $route['is_closure'],
                    ]);

                    $stats['endpoints']++;

                    // URI parameters
                    foreach ($controllerInfo['uri_parameters'] as $uriParam) {
                        ApiEndpointParameter::create([
                            'api_endpoint_id' => $endpoint->id,
                            'name' => $uriParam['name'],
                            'location' => 'uri',
                            'type' => $uriParam['type'],
                            'required' => true,
                            'description' => 'URI parameter',
                            'example' => $uriParam['type'] === 'integer' ? '1' : 'value',
                        ]);
                    }

                    // FormRequest parameters (from external project files)
                    $allParams = [];
                    $hasValidation = false;
                    if ($controllerInfo['form_request_class']) {
                        $params = $this->externalRequestParser->parse(
                            $project->project_path,
                            $controllerInfo['form_request_class'],
                        );
                        $hasValidation = ! empty($params);
                        $allParams = $params;

                        foreach ($params as $param) {
                            ApiEndpointParameter::create([
                                'api_endpoint_id' => $endpoint->id,
                                ...$param,
                            ]);
                        }
                    }

                    // Inline validation rules (when no FormRequest)
                    $inlineRules = $controllerInfo['inline_rules'] ?? [];
                    if (! $hasValidation && ! empty($inlineRules)) {
                        $params = $this->convertInlineRulesToParams($inlineRules);
                        $hasValidation = ! empty($params);
                        $allParams = $params;

                        foreach ($params as $param) {
                            ApiEndpointParameter::create([
                                'api_endpoint_id' => $endpoint->id,
                                ...$param,
                            ]);
                        }
                    }

                    // Fallback: $request->only() fields when no other validation found
                    $requestOnlyFields = $controllerInfo['request_only_fields'] ?? [];
                    if (! $hasValidation && ! empty($requestOnlyFields)) {
                        $params = $this->convertRequestOnlyToParams($requestOnlyFields);
                        $hasValidation = ! empty($params);
                        $allParams = $params;

                        foreach ($params as $param) {
                            ApiEndpointParameter::create([
                                'api_endpoint_id' => $endpoint->id,
                                ...$param,
                            ]);
                        }
                    }

                    // Responses — use analyzer for rich response examples
                    if ($route['controller_class'] && $route['controller_method']) {
                        $responses = $this->externalResponseAnalyzer->analyze(
                            $project->project_path,
                            $route['controller_class'],
                            $route['controller_method'],
                            $route['http_method'],
                            $route['uri'],
                            $hasValidation,
                            $isAuthenticated,
                            $allParams,
                        );
                    } else {
                        $responses = $this->responseGenerator->generate(
                            $route['http_method'],
                            $hasValidation,
                            $isAuthenticated,
                        );
                    }

                    foreach ($responses as $response) {
                        ApiEndpointResponse::create([
                            'api_endpoint_id' => $endpoint->id,
                            ...$response,
                        ]);
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * Convert inline validation rules to parameter arrays.
     */
    protected function convertInlineRulesToParams(array $rules): array
    {
        $typeMap = [
            'integer' => 'integer', 'numeric' => 'number', 'boolean' => 'boolean',
            'array' => 'array', 'file' => 'file', 'image' => 'file',
            'email' => 'string (email)', 'date' => 'string (date)',
            'url' => 'string (url)', 'json' => 'string (json)', 'string' => 'string',
        ];

        $params = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;

            $type = 'string';
            foreach ($typeMap as $rule => $mappedType) {
                if (in_array($rule, $ruleList)) {
                    $type = $mappedType;
                    break;
                }
            }

            $example = $this->generateInlineExample($field, $ruleList);

            $params[] = [
                'name' => $field,
                'location' => 'body',
                'type' => $type,
                'required' => in_array('required', $ruleList),
                'description' => $this->generateInlineDescription($field, $ruleList),
                'rules' => implode(', ', $ruleList),
                'example' => $example,
            ];
        }

        return $params;
    }

    /**
     * Convert $request->only() field names to parameter arrays (fallback).
     */
    protected function convertRequestOnlyToParams(array $fields): array
    {
        $params = [];

        foreach ($fields as $field) {
            $type = 'string';
            if (str_ends_with($field, '_id')) {
                $type = 'integer';
            } elseif (str_starts_with($field, 'is_') || str_starts_with($field, 'has_')) {
                $type = 'boolean';
            }

            $params[] = [
                'name' => $field,
                'location' => 'body',
                'type' => $type,
                'required' => false,
                'description' => ucfirst(str_replace(['_', '-'], ' ', $field)),
                'rules' => '',
                'example' => $this->generateInlineExample($field, []),
            ];
        }

        return $params;
    }

    /**
     * Generate an example value for an inline validation field.
     */
    protected function generateInlineExample(string $field, array $rules): string
    {
        if (in_array('email', $rules)) {
            return 'user@example.com';
        }
        if (in_array('integer', $rules)) {
            return '1';
        }
        if (in_array('numeric', $rules)) {
            return '10.5';
        }
        if (in_array('boolean', $rules)) {
            return 'true';
        }
        if (in_array('date', $rules)) {
            return '2026-01-15';
        }
        if (in_array('url', $rules)) {
            return 'https://example.com';
        }
        if (in_array('array', $rules)) {
            return '[]';
        }
        if (str_contains($field, 'name')) {
            return 'John Doe';
        }
        if (str_contains($field, 'email')) {
            return 'user@example.com';
        }
        if (str_contains($field, 'password')) {
            return 'secret123';
        }
        if (str_contains($field, 'title')) {
            return 'Sample Title';
        }
        if (str_contains($field, 'description') || str_contains($field, 'body') || str_contains($field, 'content')) {
            return 'Lorem ipsum dolor sit amet...';
        }
        if (str_ends_with($field, '_id')) {
            return '1';
        }

        return 'example_value';
    }

    /**
     * Generate a description for an inline validation field.
     */
    protected function generateInlineDescription(string $field, array $rules): string
    {
        $label = ucfirst(str_replace(['_', '-'], ' ', $field));
        $parts = [$label];

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'max:')) {
                $parts[] = 'max '.substr($rule, 4).' chars';
            }
            if (str_starts_with($rule, 'min:')) {
                $parts[] = 'min '.substr($rule, 4);
            }
            if ($rule === 'unique' || str_starts_with($rule, 'unique:')) {
                $parts[] = 'must be unique';
            }
            if (str_starts_with($rule, 'in:')) {
                $parts[] = 'one of: '.str_replace(',', ', ', substr($rule, 3));
            }
            if ($rule === 'confirmed') {
                $parts[] = 'must be confirmed';
            }
        }

        return implode('. ', $parts);
    }

    protected function hasAuthMiddleware(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (in_array($m, ['auth', 'auth:sanctum', 'auth:api'])) {
                return true;
            }
            if (str_starts_with($m, 'auth:')) {
                return true;
            }
        }

        return false;
    }
}
