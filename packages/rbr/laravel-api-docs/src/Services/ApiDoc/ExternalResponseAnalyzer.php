<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

class ExternalResponseAnalyzer
{
    public function __construct(
        protected ExternalResourceParser $resourceParser,
    ) {}

    /**
     * Generate realistic response examples by analyzing the controller method,
     * model files, and migration files from the external project.
     */
    public function analyze(
        string $projectPath,
        string $controllerClass,
        string $method,
        string $httpMethod,
        string $uri,
        bool $hasValidation,
        bool $isAuthenticated,
        array $requestParams = [],
    ): array {
        $responses = [];

        // Read the controller file to detect which model is used
        $controllerFile = $this->resolveClassFile($projectPath, $controllerClass);
        $controllerContent = $controllerFile ? file_get_contents($controllerFile) : '';

        $modelInfo = $this->detectModel($projectPath, $controllerContent, $method, $uri);
        $modelFields = $modelInfo ? $this->extractModelFields($projectPath, $modelInfo) : [];

        // Detect response pattern (wrapper style, Resource class)
        $responsePattern = $this->detectResponsePattern($controllerContent, $method, $projectPath);

        // If a Resource class was detected, use its fields
        $resourceFields = [];
        if ($responsePattern && ! empty($responsePattern['resource_class'])) {
            $parsed = $this->resourceParser->parse($projectPath, $responsePattern['resource_class']);
            $resourceFields = $parsed['fields'] ?? [];
            if ($parsed['has_wrapper'] ?? false) {
                $responsePattern['has_wrapper'] = true;
            }
        }

        // Build success response with realistic body
        $successResponse = $this->buildSuccessResponse(
            $httpMethod, $method, $modelInfo, $modelFields, $requestParams, $uri,
            $responsePattern, $resourceFields,
        );
        $responses[] = $successResponse;

        // 422 if endpoint has validation
        if ($hasValidation && in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            $firstParam = $requestParams[0]['name'] ?? 'field';
            $responses[] = [
                'status_code' => 422,
                'description' => 'Validation Error',
                'content_type' => 'application/json',
                'example_body' => [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        $firstParam => ["The {$firstParam} field is required."],
                    ],
                ],
            ];
        }

        // 401 if authenticated
        if ($isAuthenticated) {
            $responses[] = [
                'status_code' => 401,
                'description' => 'Unauthenticated',
                'content_type' => 'application/json',
                'example_body' => [
                    'message' => 'Unauthenticated.',
                ],
            ];
        }

        // 404 for show/update/delete endpoints with URI params
        if (preg_match('/\{/', $uri) && in_array($method, ['show', 'update', 'edit', 'destroy'])) {
            $resourceName = $modelInfo['short_name'] ?? 'Resource';
            $responses[] = [
                'status_code' => 404,
                'description' => 'Not Found',
                'content_type' => 'application/json',
                'example_body' => [
                    'message' => "{$resourceName} not found.",
                ],
            ];
        }

        return $responses;
    }

    /**
     * Detect the response pattern used by the controller method.
     * Looks for Resource classes, wrapper patterns (status/message/data), etc.
     */
    protected function detectResponsePattern(string $controllerContent, string $method, string $projectPath): ?array
    {
        if (empty($controllerContent)) {
            return null;
        }

        // Extract the method body
        $pattern = '/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/s';
        if (! preg_match($pattern, $controllerContent, $methodStart, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $startPos = $methodStart[0][1] + strlen($methodStart[0][0]) - 1;
        $methodBody = $this->extractBraceBlock($controllerContent, $startPos);

        $result = ['has_wrapper' => false, 'resource_class' => null];

        // Detect Resource class usage: new XxxResource(...) or XxxResource::collection(...)
        if (preg_match('/new\s+([\w\\\\]+Resource)\s*\(/', $methodBody, $match)) {
            $shortName = basename(str_replace('\\', '/', $match[1]));
            $result['resource_class'] = $this->resolveClassName($controllerContent, $shortName);
        } elseif (preg_match('/([\w\\\\]+Resource)::collection\s*\(/', $methodBody, $match)) {
            $shortName = basename(str_replace('\\', '/', $match[1]));
            $result['resource_class'] = $this->resolveClassName($controllerContent, $shortName);
        }

        // Detect wrapper pattern: response()->json(['status' => ..., 'message' => ...])
        if (preg_match('/response\(\)->json\s*\(\s*\[/', $methodBody) ||
            preg_match('/jsonResponse\s*\(/', $methodBody)) {
            // Check if the response includes status/message keys
            if (str_contains($methodBody, "'status'") && str_contains($methodBody, "'message'")) {
                $result['has_wrapper'] = true;
            }
        }

        // Also detect helper method patterns like $this->jsonResponse()
        if (preg_match('/\$this->(jsonResponse|sendResponse|successResponse|apiResponse)\s*\(/', $methodBody)) {
            $result['has_wrapper'] = true;
        }

        return $result;
    }

    /**
     * Build a success response with realistic example body based on model fields.
     */
    protected function buildSuccessResponse(
        string $httpMethod,
        string $method,
        ?array $modelInfo,
        array $modelFields,
        array $requestParams,
        string $uri,
        ?array $responsePattern = null,
        array $resourceFields = [],
    ): array {
        $hasWrapper = $responsePattern['has_wrapper'] ?? false;

        if ($httpMethod === 'DELETE') {
            $body = ['message' => ($modelInfo['short_name'] ?? 'Resource').' deleted successfully.'];
            if ($hasWrapper) {
                $body = ['status' => 200, 'message' => ($modelInfo['short_name'] ?? 'Resource').' deleted successfully.'];
            }

            return [
                'status_code' => 200,
                'description' => 'OK',
                'content_type' => 'application/json',
                'example_body' => $body,
            ];
        }

        // Use resource fields if available, otherwise fall back to model fields
        $exampleData = ! empty($resourceFields)
            ? $this->buildExampleDataFromResourceFields($resourceFields)
            : $this->buildExampleData($modelFields, $requestParams);

        if ($httpMethod === 'POST') {
            $statusCode = 201;
            $description = 'Created';
            $body = ! empty($exampleData)
                ? ['data' => $exampleData]
                : ['message' => ($modelInfo['short_name'] ?? 'Resource').' created successfully.'];

            if ($hasWrapper) {
                $body = [
                    'status' => $statusCode,
                    'message' => ($modelInfo['short_name'] ?? 'Resource').' created successfully.',
                    'data' => ! empty($exampleData) ? $exampleData : new \stdClass,
                ];
            }

            return [
                'status_code' => $statusCode,
                'description' => $description,
                'content_type' => 'application/json',
                'example_body' => $body,
            ];
        }

        // GET - index (list) vs show (single)
        if (in_array($method, ['index', 'list', 'all'])) {
            $body = ! empty($exampleData)
                ? [
                    'data' => [$exampleData],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 1,
                    ],
                ]
                : ['data' => []];

            if ($hasWrapper) {
                $body = [
                    'status' => 200,
                    'message' => 'Success',
                    'data' => ! empty($exampleData) ? [$exampleData] : [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 1,
                    ],
                ];
            }

            return [
                'status_code' => 200,
                'description' => 'OK',
                'content_type' => 'application/json',
                'example_body' => $body,
            ];
        }

        // PUT/PATCH
        if (in_array($httpMethod, ['PUT', 'PATCH'])) {
            $body = ! empty($exampleData)
                ? ['data' => $exampleData]
                : ['message' => ($modelInfo['short_name'] ?? 'Resource').' updated successfully.'];

            if ($hasWrapper) {
                $body = [
                    'status' => 200,
                    'message' => ($modelInfo['short_name'] ?? 'Resource').' updated successfully.',
                    'data' => ! empty($exampleData) ? $exampleData : new \stdClass,
                ];
            }

            return [
                'status_code' => 200,
                'description' => 'OK',
                'content_type' => 'application/json',
                'example_body' => $body,
            ];
        }

        // GET show or other
        $body = ! empty($exampleData)
            ? ['data' => $exampleData]
            : ['data' => '...'];

        if ($hasWrapper) {
            $body = [
                'status' => 200,
                'message' => 'Success',
                'data' => ! empty($exampleData) ? $exampleData : new \stdClass,
            ];
        }

        return [
            'status_code' => 200,
            'description' => 'OK',
            'content_type' => 'application/json',
            'example_body' => $body,
        ];
    }

    /**
     * Build example data from Resource field definitions.
     */
    protected function buildExampleDataFromResourceFields(array $resourceFields): array
    {
        $data = [];

        foreach ($resourceFields as $field) {
            $data[$field['name']] = $this->generateFieldExample($field['name'], $field['type']);
        }

        return $data;
    }

    /**
     * Build example data from model fields and request params.
     */
    protected function buildExampleData(array $modelFields, array $requestParams): array
    {
        $data = [];

        if (! empty($modelFields)) {
            $data['id'] = 1;

            foreach ($modelFields as $field) {
                $data[$field['name']] = $this->generateFieldExample($field['name'], $field['type']);
            }

            $data['created_at'] = '2026-01-15T10:30:00.000000Z';
            $data['updated_at'] = '2026-01-15T10:30:00.000000Z';

            return $data;
        }

        // Fallback: build from request params
        if (! empty($requestParams)) {
            $data['id'] = 1;

            foreach ($requestParams as $param) {
                if ($param['location'] === 'body') {
                    $data[$param['name']] = $param['example'] ?? $this->generateFieldExample($param['name'], $param['type'] ?? 'string');
                }
            }

            if (! empty($data) && count($data) > 1) {
                $data['created_at'] = '2026-01-15T10:30:00.000000Z';
                $data['updated_at'] = '2026-01-15T10:30:00.000000Z';
            }

            return $data;
        }

        return $data;
    }

    /**
     * Detect which model the controller method uses.
     */
    protected function detectModel(string $projectPath, string $controllerContent, string $method, string $uri): ?array
    {
        if (empty($controllerContent)) {
            return $this->guessModelFromUri($uri);
        }

        // Extract the method body
        $pattern = '/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/s';
        if (! preg_match($pattern, $controllerContent, $methodStart, PREG_OFFSET_CAPTURE)) {
            return $this->guessModelFromUri($uri);
        }

        $startPos = $methodStart[0][1] + strlen($methodStart[0][0]) - 1;
        $methodBody = $this->extractBraceBlock($controllerContent, $startPos);

        // Look for Model::method() calls in the method body
        if (preg_match('/(\w+)::(create|find|findOrFail|all|paginate|where|query|get)/', $methodBody, $match)) {
            $shortName = $match[1];
            $fqcn = $this->resolveClassName($controllerContent, $shortName);

            return ['class' => $fqcn, 'short_name' => $shortName];
        }

        // Look for $model->method() patterns like $user->save(), $post->update()
        if (preg_match('/\$(\w+)->(save|update|delete|create|toArray|load)\b/', $methodBody, $match)) {
            $varName = $match[1];
            $signaturePattern = '/function\s+'.preg_quote($method, '/').'\s*\(([^)]*)\)/s';
            if (preg_match($signaturePattern, $controllerContent, $sigMatch)) {
                if (preg_match('/([\w\\\\]+)\s+\$'.preg_quote($varName, '/').'/', $sigMatch[1], $typeMatch)) {
                    $shortName = basename(str_replace('\\', '/', $typeMatch[1]));
                    if (! str_contains($shortName, 'Request')) {
                        $fqcn = $this->resolveClassName($controllerContent, $shortName);

                        return ['class' => $fqcn, 'short_name' => $shortName];
                    }
                }
            }
        }

        // Look for new Model() pattern
        if (preg_match('/new\s+(\w+)\s*\(/', $methodBody, $match)) {
            $shortName = $match[1];
            if (! str_contains($shortName, 'Request') && ! str_contains($shortName, 'Response') && ! str_contains($shortName, 'Resource')) {
                $fqcn = $this->resolveClassName($controllerContent, $shortName);

                return ['class' => $fqcn, 'short_name' => $shortName];
            }
        }

        return $this->guessModelFromUri($uri);
    }

    /**
     * Guess the model name from the URI.
     */
    protected function guessModelFromUri(string $uri): ?array
    {
        $segments = explode('/', trim($uri, '/'));

        $resourceSegment = null;
        foreach (array_reverse($segments) as $segment) {
            if (! str_starts_with($segment, '{')) {
                $resourceSegment = $segment;
                break;
            }
        }

        if (! $resourceSegment) {
            return null;
        }

        $singular = rtrim($resourceSegment, 's');
        $shortName = str_replace(' ', '', ucwords(str_replace('-', ' ', $singular)));

        return ['class' => 'App\\Models\\'.$shortName, 'short_name' => $shortName];
    }

    /**
     * Extract model fields from the model's $fillable/$casts and migration files.
     */
    protected function extractModelFields(string $projectPath, array $modelInfo): array
    {
        $fields = [];

        $modelFile = $this->resolveClassFile($projectPath, $modelInfo['class']);
        if ($modelFile && file_exists($modelFile)) {
            $modelContent = file_get_contents($modelFile);
            $fields = $this->extractFillableFields($modelContent);

            $casts = $this->extractCasts($modelContent);
            foreach ($fields as &$field) {
                if (isset($casts[$field['name']])) {
                    $field['type'] = $this->castToType($casts[$field['name']]);
                }
            }
        }

        $migrationFields = $this->extractMigrationFields($projectPath, $modelInfo['short_name']);
        if (! empty($migrationFields)) {
            if (empty($fields)) {
                $fields = $migrationFields;
            } else {
                $migrationMap = [];
                foreach ($migrationFields as $mf) {
                    $migrationMap[$mf['name']] = $mf['type'];
                }
                foreach ($fields as &$field) {
                    if ($field['type'] === 'string' && isset($migrationMap[$field['name']])) {
                        $field['type'] = $migrationMap[$field['name']];
                    }
                }
            }
        }

        return $fields;
    }

    protected function extractFillableFields(string $content): array
    {
        if (! preg_match('/\$fillable\s*=\s*\[(.*?)\]/s', $content, $match)) {
            return [];
        }

        preg_match_all("/['\"](\w+)['\"]/", $match[1], $fieldMatches);

        $fields = [];
        foreach ($fieldMatches[1] ?? [] as $name) {
            $fields[] = ['name' => $name, 'type' => 'string'];
        }

        return $fields;
    }

    protected function extractCasts(string $content): array
    {
        $casts = [];

        if (preg_match('/\$casts\s*=\s*\[(.*?)\]/s', $content, $match)
            || preg_match('/function\s+casts\s*\(\s*\).*?return\s*\[(.*?)\]/s', $content, $match)) {

            preg_match_all("/['\"](\w+)['\"]\s*=>\s*['\"](\w+)['\"]/", $match[1], $castMatches, PREG_SET_ORDER);
            foreach ($castMatches as $cm) {
                $casts[$cm[1]] = $cm[2];
            }
        }

        return $casts;
    }

    protected function castToType(string $cast): string
    {
        return match ($cast) {
            'integer', 'int' => 'integer',
            'float', 'double', 'decimal' => 'number',
            'boolean', 'bool' => 'boolean',
            'array', 'json', 'collection' => 'array',
            'date', 'datetime', 'timestamp' => 'datetime',
            default => 'string',
        };
    }

    protected function extractMigrationFields(string $projectPath, string $modelName): array
    {
        $migrationsPath = rtrim($projectPath, '/').'/database/migrations';
        if (! is_dir($migrationsPath)) {
            return [];
        }

        $tableName = $this->modelToTableName($modelName);

        $migrationFiles = glob($migrationsPath.'/*.php');
        $targetFile = null;

        foreach ($migrationFiles as $file) {
            $filename = basename($file);
            if (str_contains($filename, "create_{$tableName}_table")) {
                $targetFile = $file;
                break;
            }
        }

        if (! $targetFile) {
            return [];
        }

        $content = file_get_contents($targetFile);

        return $this->parseMigrationColumns($content);
    }

    protected function parseMigrationColumns(string $content): array
    {
        $fields = [];
        $skipColumns = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'email_verified_at', 'password'];

        $pattern = '/\$table->(string|integer|bigInteger|tinyInteger|smallInteger|boolean|text|longText|mediumText|float|double|decimal|date|dateTime|timestamp|json|jsonb|uuid|enum|unsignedBigInteger|unsignedInteger|foreignId)\(\s*[\'"](\w+)[\'"]/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $columnType = $match[1];
            $columnName = $match[2];

            if (in_array($columnName, $skipColumns)) {
                continue;
            }

            $fields[] = [
                'name' => $columnName,
                'type' => $this->migrationColumnToType($columnType),
            ];
        }

        return $fields;
    }

    protected function migrationColumnToType(string $columnType): string
    {
        return match ($columnType) {
            'integer', 'bigInteger', 'tinyInteger', 'smallInteger', 'unsignedBigInteger', 'unsignedInteger', 'foreignId' => 'integer',
            'float', 'double', 'decimal' => 'number',
            'boolean' => 'boolean',
            'date' => 'date',
            'dateTime', 'timestamp' => 'datetime',
            'json', 'jsonb' => 'array',
            'text', 'longText', 'mediumText' => 'text',
            default => 'string',
        };
    }

    protected function modelToTableName(string $modelName): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));

        if (str_ends_with($snake, 'y') && ! str_ends_with($snake, 'ey') && ! str_ends_with($snake, 'ay') && ! str_ends_with($snake, 'oy')) {
            return substr($snake, 0, -1).'ies';
        }
        if (str_ends_with($snake, 's') || str_ends_with($snake, 'sh') || str_ends_with($snake, 'ch') || str_ends_with($snake, 'x')) {
            return $snake.'es';
        }

        return $snake.'s';
    }

    protected function generateFieldExample(string $name, string $type): mixed
    {
        $nameExamples = [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'phone' => '+1-555-0123',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'zip' => '10001',
            'zip_code' => '10001',
            'postal_code' => '10001',
            'title' => 'Sample Title',
            'slug' => 'sample-title',
            'body' => 'Lorem ipsum dolor sit amet...',
            'content' => 'Lorem ipsum dolor sit amet...',
            'summary' => 'A brief summary of the content.',
            'description' => 'A detailed description.',
            'status' => 'active',
            'type' => 'default',
            'role' => 'user',
            'avatar' => 'https://example.com/avatar.jpg',
            'image' => 'https://example.com/image.jpg',
            'url' => 'https://example.com',
            'website' => 'https://example.com',
            'price' => 29.99,
            'amount' => 100.00,
            'total' => 150.00,
            'quantity' => 1,
            'count' => 10,
            'age' => 25,
            'weight' => 70.5,
            'height' => 175,
            'latitude' => 40.7128,
            'longitude' => -74.006,
            'lat' => 40.7128,
            'lng' => -74.006,
            'color' => '#3498db',
            'token' => 'abc123def456',
            'api_key' => 'sk_test_abc123',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ];

        if (isset($nameExamples[$name])) {
            return $nameExamples[$name];
        }

        foreach ($nameExamples as $key => $example) {
            if (str_contains($name, $key)) {
                return $example;
            }
        }

        if (str_ends_with($name, '_id') || str_ends_with($name, 'Id')) {
            return 1;
        }
        if (str_ends_with($name, '_at')) {
            return '2026-01-15T10:30:00.000000Z';
        }
        if (str_ends_with($name, '_date')) {
            return '2026-01-15';
        }
        if (str_ends_with($name, '_url') || str_ends_with($name, '_link')) {
            return 'https://example.com';
        }
        if (str_ends_with($name, '_count') || str_ends_with($name, '_number')) {
            return 5;
        }
        if (str_starts_with($name, 'is_') || str_starts_with($name, 'has_') || str_starts_with($name, 'can_')) {
            return true;
        }

        return match ($type) {
            'integer' => 1,
            'number', 'float', 'double', 'decimal' => 10.5,
            'boolean' => true,
            'array', 'json' => [],
            'object' => new \stdClass,
            'date' => '2026-01-15',
            'datetime', 'timestamp' => '2026-01-15T10:30:00.000000Z',
            'text' => 'Lorem ipsum dolor sit amet...',
            default => 'example_value',
        };
    }

    protected function resolveClassFile(string $projectPath, string $className): ?string
    {
        if (str_starts_with($className, 'App\\')) {
            $relativePath = str_replace('\\', '/', substr($className, 4)).'.php';
            $filePath = rtrim($projectPath, '/').'/app/'.$relativePath;
            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        $composerPath = rtrim($projectPath, '/').'/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            foreach ($composer['autoload']['psr-4'] ?? [] as $namespace => $path) {
                if (str_starts_with($className, $namespace)) {
                    $relativePath = str_replace('\\', '/', substr($className, strlen($namespace))).'.php';
                    $filePath = rtrim($projectPath, '/').'/'.rtrim($path, '/').'/'.$relativePath;
                    if (file_exists($filePath)) {
                        return $filePath;
                    }
                }
            }
        }

        return null;
    }

    protected function resolveClassName(string $content, string $shortName): string
    {
        $shortName = ltrim($shortName, '\\');
        if (str_contains($shortName, '\\')) {
            return $shortName;
        }

        if (preg_match('/^use\s+([\w\\\\]+\\\\'.preg_quote($shortName, '/').')\s*;/m', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $matches)) {
            return $matches[1].'\\'.$shortName;
        }

        return 'App\\Models\\'.$shortName;
    }

    protected function extractBraceBlock(string $content, int $startPos): string
    {
        $depth = 0;
        $length = strlen($content);
        $blockStart = $startPos;

        for ($i = $startPos; $i < $length; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $blockStart, $i - $blockStart + 1);
                }
            }
        }

        return substr($content, $blockStart);
    }
}
