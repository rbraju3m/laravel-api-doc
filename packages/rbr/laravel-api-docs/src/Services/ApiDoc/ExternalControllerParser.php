<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

class ExternalControllerParser
{
    /**
     * Parse a controller method from an external project's filesystem.
     */
    public function parse(string $projectPath, string $controllerClass, string $method, string $uri = ''): array
    {
        $result = [
            'description' => null,
            'form_request_class' => null,
            'inline_rules' => [],
            'uri_parameters' => [],
            'request_only_fields' => [],
        ];

        $filePath = $this->resolveClassFile($projectPath, $controllerClass);

        if (! $filePath || ! file_exists($filePath)) {
            return $result;
        }

        $content = file_get_contents($filePath);

        // Extract docblock for the method
        $result['description'] = $this->extractDocblock($content, $method);

        // Extract FormRequest type-hint from method signature
        $result['form_request_class'] = $this->extractFormRequest($content, $method, $projectPath);

        // If no FormRequest, try to extract inline validation rules
        if (! $result['form_request_class']) {
            $result['inline_rules'] = $this->extractInlineValidation($content, $method);
        }

        // If still no validation, try to extract $request->only() fields as fallback
        if (! $result['form_request_class'] && empty($result['inline_rules'])) {
            $result['request_only_fields'] = $this->extractRequestOnlyFields($content, $method);
        }

        // Extract URI parameters from method signature + URI placeholders
        $result['uri_parameters'] = $this->extractUriParameters($content, $method, $uri);

        return $result;
    }

    /**
     * Resolve a PSR-4 class name to a file path within the project.
     */
    protected function resolveClassFile(string $projectPath, string $className): ?string
    {
        // Try App\ namespace → app/ directory (standard Laravel convention)
        if (str_starts_with($className, 'App\\')) {
            $relativePath = str_replace('\\', '/', substr($className, 4)).'.php';
            $filePath = rtrim($projectPath, '/').'/app/'.$relativePath;

            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        // Try to resolve via composer autoload
        $composerPath = rtrim($projectPath, '/').'/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            $autoload = $composer['autoload']['psr-4'] ?? [];

            foreach ($autoload as $namespace => $path) {
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

    /**
     * Extract docblock description for a method using regex.
     */
    protected function extractDocblock(string $content, string $method): ?string
    {
        // Match docblock immediately before the method declaration
        $pattern = '/\/\*\*(.*?)\*\/\s*(?:public|protected|private)\s+function\s+'.preg_quote($method, '/').'\s*\(/s';

        if (! preg_match($pattern, $content, $matches)) {
            return null;
        }

        $docblock = $matches[1];
        $lines = explode("\n", $docblock);
        $description = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");

            if (empty($line)) {
                continue;
            }

            if (str_starts_with($line, '@')) {
                break;
            }

            $description[] = $line;
        }

        return ! empty($description) ? implode(' ', $description) : null;
    }

    /**
     * Extract FormRequest type-hint from a method signature.
     */
    protected function extractFormRequest(string $content, string $method, string $projectPath): ?string
    {
        // Match method signature parameters
        $pattern = '/function\s+'.preg_quote($method, '/').'\s*\(([^)]*)\)/s';

        if (! preg_match($pattern, $content, $matches)) {
            return null;
        }

        $params = $matches[1];

        // Find type-hinted parameters that look like FormRequest classes
        if (preg_match_all('/(?:\\\\?[\w\\\\]+Request)\s+\$\w+/', $params, $typeMatches)) {
            foreach ($typeMatches[0] as $match) {
                $typeName = trim(explode('$', $match)[0]);

                // Resolve to fully qualified class name using use statements
                $fqcn = $this->resolveClassName($content, $typeName);

                // Verify it extends FormRequest by checking the file
                if ($this->isFormRequest($projectPath, $fqcn)) {
                    return $fqcn;
                }
            }
        }

        return null;
    }

    /**
     * Extract URI parameters from method signature and URI placeholders.
     */
    protected function extractUriParameters(string $content, string $method, string $uri = ''): array
    {
        $uriParams = [];
        $foundNames = [];

        // Get URI placeholders like {product}, {id}
        $uriPlaceholders = [];
        if (preg_match_all('/\{(\w+)\}/', $uri, $placeholderMatches)) {
            $uriPlaceholders = $placeholderMatches[1];
        }

        $pattern = '/function\s+'.preg_quote($method, '/').'\s*\(([^)]*)\)/s';
        if (preg_match($pattern, $content, $matches)) {
            $params = $matches[1];
            $builtinTypes = ['int', 'string', 'float', 'bool'];

            // Match typed parameters like "int $id", "string $slug"
            if (preg_match_all('/\b('.implode('|', $builtinTypes).')\s+\$(\w+)/', $params, $paramMatches, PREG_SET_ORDER)) {
                foreach ($paramMatches as $match) {
                    $type = $match[1] === 'int' ? 'integer' : $match[1];
                    $uriParams[] = [
                        'name' => $match[2],
                        'type' => $type,
                    ];
                    $foundNames[] = $match[2];
                }
            }

            // Detect model route bindings (non-builtin, non-Request type-hints)
            if (preg_match_all('/(?:\\\\?[\w\\\\]+)\s+\$(\w+)/', $params, $allParams, PREG_SET_ORDER)) {
                foreach ($allParams as $match) {
                    $typePart = trim(explode('$', $match[0])[0]);
                    if (in_array($typePart, $builtinTypes) || str_contains($typePart, 'Request')) {
                        continue;
                    }
                    if (! in_array($match[1], $foundNames)) {
                        $uriParams[] = [
                            'name' => $match[1],
                            'type' => 'integer',
                        ];
                        $foundNames[] = $match[1];
                    }
                }
            }

            // Collect untyped parameters (non-Request, no type-hint)
            $untypedParams = [];
            if (preg_match_all('/,\s*\$(\w+)/', $params, $untypedMatches)) {
                foreach ($untypedMatches[1] as $untypedName) {
                    if (! in_array($untypedName, $foundNames)) {
                        $untypedParams[] = $untypedName;
                    }
                }
            }

            // Map untyped params to URI placeholders by position
            $remainingPlaceholders = array_values(array_diff($uriPlaceholders, $foundNames));
            foreach ($untypedParams as $index => $untypedName) {
                if (isset($remainingPlaceholders[$index])) {
                    $uriParams[] = [
                        'name' => $remainingPlaceholders[$index],
                        'type' => 'string',
                    ];
                    $foundNames[] = $remainingPlaceholders[$index];
                    $foundNames[] = $untypedName;
                } elseif (! in_array($untypedName, $foundNames)) {
                    $uriParams[] = [
                        'name' => $untypedName,
                        'type' => 'string',
                    ];
                    $foundNames[] = $untypedName;
                }
            }
        }

        // Add any remaining URI placeholders not found via method params
        foreach ($uriPlaceholders as $placeholder) {
            if (! in_array($placeholder, $foundNames)) {
                $uriParams[] = [
                    'name' => $placeholder,
                    'type' => 'string',
                ];
            }
        }

        return $uriParams;
    }

    /**
     * Extract inline validation rules from a method body.
     * Handles: $request->validate([...]), $this->validate($request, [...]),
     * Validator::make($data, [...]), validationRules() method calls, and other common patterns.
     */
    protected function extractInlineValidation(string $content, string $method): array
    {
        // Extract the method body
        $pattern = '/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/s';
        if (! preg_match($pattern, $content, $methodStart, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $startPos = $methodStart[0][1] + strlen($methodStart[0][0]) - 1;
        $methodBody = $this->extractBraceBlock($content, $startPos);

        // Find the validation rules array using multiple patterns
        $rulesArray = $this->findValidationRulesArray($methodBody, $content);

        if (! $rulesArray) {
            return [];
        }

        return $this->parseRulesArray($rulesArray);
    }

    /**
     * Find the validation rules array from the method body.
     * Supports: $request->validate([...]), $this->validate($request, [...]),
     * Validator::make($data, [...]), $this->validationRules() delegation
     */
    protected function findValidationRulesArray(string $methodBody, string $fullContent = ''): ?string
    {
        // Pattern 1: $request->validate([...])
        if (preg_match('/\$request->validate\s*\(\s*\[/s', $methodBody, $match, PREG_OFFSET_CAPTURE)) {
            $bracketStart = strpos($methodBody, '[', $match[0][1]);

            return $this->extractBracketBlock($methodBody, $bracketStart);
        }

        // Pattern 2: $this->validate($request, [...]) — ValidatesRequests trait
        if (preg_match('/\$this->validate\s*\(\s*\$\w+\s*,\s*\[/s', $methodBody, $match, PREG_OFFSET_CAPTURE)) {
            $bracketStart = $match[0][1] + strlen($match[0][0]) - 1;

            return $this->extractBracketBlock($methodBody, $bracketStart);
        }

        // Pattern 3: Validator::make($data, [...])
        if (preg_match('/Validator::make\s*\([^,]+,\s*\[/s', $methodBody, $match, PREG_OFFSET_CAPTURE)) {
            $bracketStart = $match[0][1] + strlen($match[0][0]) - 1;

            return $this->extractBracketBlock($methodBody, $bracketStart);
        }

        // Pattern 4: validate([...]) — generic call
        if (preg_match('/->validate\s*\(\s*\[/s', $methodBody, $match, PREG_OFFSET_CAPTURE)) {
            $bracketStart = strpos($methodBody, '[', $match[0][1]);

            return $this->extractBracketBlock($methodBody, $bracketStart);
        }

        // Pattern 5: $this->validate($request, $this->validationRules(...)) or similar method delegation
        if ($fullContent && preg_match('/\$this->(?:validate\s*\(\s*\$\w+\s*,\s*\$this->|)(\w+Rules|validationRules|getRules|rules)\s*\(/', $methodBody, $match)) {
            $methodName = $match[1];

            return $this->extractRulesFromMethod($fullContent, $methodName);
        }

        return null;
    }

    /**
     * Extract rules array from a named method in the same file.
     * e.g. validationRules() { return [...]; }
     */
    protected function extractRulesFromMethod(string $content, string $methodName): ?string
    {
        $pattern = '/function\s+'.preg_quote($methodName, '/').'\s*\([^)]*\)[^{]*\{/s';
        if (! preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $startPos = $match[0][1] + strlen($match[0][0]) - 1;
        $methodBody = $this->extractBraceBlock($content, $startPos);

        // Look for return [...]
        if (preg_match('/return\s*\[/s', $methodBody, $returnMatch, PREG_OFFSET_CAPTURE)) {
            $bracketStart = strpos($methodBody, '[', $returnMatch[0][1]);

            return $this->extractBracketBlock($methodBody, $bracketStart);
        }

        return null;
    }

    /**
     * Extract $request->only() field names from a method body as fallback params.
     */
    protected function extractRequestOnlyFields(string $content, string $method): array
    {
        $pattern = '/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/s';
        if (! preg_match($pattern, $content, $methodStart, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $startPos = $methodStart[0][1] + strlen($methodStart[0][0]) - 1;
        $methodBody = $this->extractBraceBlock($content, $startPos);

        $fields = [];

        // Match $request->only('field1', 'field2') or $request->only(['field1', 'field2'])
        if (preg_match('/\$request->only\s*\(\s*(\[.*?\]|[^)]+)\)/s', $methodBody, $match)) {
            $argContent = $match[1];
            preg_match_all("/['\"](\w+)['\"]/", $argContent, $fieldMatches);
            $fields = $fieldMatches[1] ?? [];
        }

        return $fields;
    }

    /**
     * Extract content within balanced square brackets starting at the given position.
     * Returns the content INSIDE the brackets (without outer [ ]).
     */
    protected function extractBracketBlock(string $content, int $startPos): ?string
    {
        if (! isset($content[$startPos]) || $content[$startPos] !== '[') {
            return null;
        }

        $depth = 0;
        $length = strlen($content);

        for ($i = $startPos; $i < $length; $i++) {
            if ($content[$i] === '[') {
                $depth++;
            } elseif ($content[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $startPos + 1, $i - $startPos - 1);
                }
            }
        }

        return null;
    }

    /**
     * Parse a rules array string into field => rules mapping.
     */
    protected function parseRulesArray(string $arrayContent): array
    {
        $rules = [];

        // Match 'field' => 'rule|rule' or 'field' => ['rule', 'rule']
        preg_match_all("/['\"](\w[\w.*]*?)['\"]\s*=>\s*(\[.*?\]|'[^']*'|\"[^\"]*\")/s", $arrayContent, $fieldMatches, PREG_SET_ORDER);

        foreach ($fieldMatches as $fieldMatch) {
            $field = $fieldMatch[1];
            $rulesValue = trim($fieldMatch[2]);

            if (str_starts_with($rulesValue, '[')) {
                preg_match_all("/['\"]([^'\"]+)['\"]/", $rulesValue, $ruleItems);
                $rules[$field] = $ruleItems[1] ?? [];
            } else {
                $rulesStr = trim($rulesValue, "'\"");
                $rules[$field] = explode('|', $rulesStr);
            }
        }

        return $rules;
    }

    /**
     * Extract content within balanced braces starting at the given position.
     */
    protected function extractBraceBlock(string $content, int $startPos): string
    {
        $depth = 0;
        $length = strlen($content);

        for ($i = $startPos; $i < $length; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $startPos, $i - $startPos + 1);
                }
            }
        }

        return substr($content, $startPos);
    }

    /**
     * Resolve a short class name to FQCN using use statements in the file.
     */
    protected function resolveClassName(string $content, string $shortName): string
    {
        $shortName = ltrim($shortName, '\\');

        // If already fully qualified
        if (str_contains($shortName, '\\')) {
            return $shortName;
        }

        // Search use statements
        if (preg_match('/^use\s+([\w\\\\]+\\\\'.preg_quote($shortName, '/').')\s*;/m', $content, $matches)) {
            return $matches[1];
        }

        // Try same namespace
        if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $matches)) {
            return $matches[1].'\\'.$shortName;
        }

        return $shortName;
    }

    /**
     * Check if a class extends FormRequest by reading its file.
     */
    protected function isFormRequest(string $projectPath, string $className): bool
    {
        $filePath = $this->resolveClassFile($projectPath, $className);

        if (! $filePath || ! file_exists($filePath)) {
            // If we can't find the file, assume it's a FormRequest if the name ends with Request
            return str_ends_with($className, 'Request');
        }

        $content = file_get_contents($filePath);

        return str_contains($content, 'extends FormRequest')
            || str_contains($content, 'extends \Illuminate\Foundation\Http\FormRequest');
    }
}
