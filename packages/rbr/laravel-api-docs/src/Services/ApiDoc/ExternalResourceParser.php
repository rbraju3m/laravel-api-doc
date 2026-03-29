<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

class ExternalResourceParser
{
    /**
     * Parse an API Resource class to extract toArray() field definitions.
     */
    public function parse(string $projectPath, string $resourceClass): array
    {
        $filePath = $this->resolveClassFile($projectPath, $resourceClass);

        if (! $filePath || ! file_exists($filePath)) {
            return ['fields' => [], 'has_wrapper' => false];
        }

        $content = file_get_contents($filePath);

        $fields = $this->extractToArrayFields($content);
        $hasWrapper = $this->detectWrapper($content);

        return ['fields' => $fields, 'has_wrapper' => $hasWrapper];
    }

    /**
     * Extract fields from the toArray() method's return statement.
     */
    protected function extractToArrayFields(string $content): array
    {
        // Find toArray method
        $pattern = '/function\s+toArray\s*\([^)]*\)[^{]*\{/s';
        if (! preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $startPos = $match[0][1] + strlen($match[0][0]) - 1;
        $methodBody = $this->extractBraceBlock($content, $startPos);

        // Find the return [...] statement
        if (! preg_match('/return\s*\[/s', $methodBody, $returnMatch, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $bracketStart = strpos($methodBody, '[', $returnMatch[0][1]);
        $arrayContent = $this->extractBracketBlock($methodBody, $bracketStart);

        if (! $arrayContent) {
            return [];
        }

        return $this->parseFieldDefinitions($arrayContent);
    }

    /**
     * Parse field definitions from the return array content.
     * e.g. 'id' => $this->id, 'name' => $this->name
     */
    protected function parseFieldDefinitions(string $arrayContent): array
    {
        $fields = [];

        // Match 'key' => expression patterns
        preg_match_all("/['\"](\w+)['\"]\s*=>\s*([^,\n]+)/", $arrayContent, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $expression = trim($match[2]);

            // Skip nested arrays/conditionals that are complex structures
            if ($name === 'data' || $name === 'meta') {
                continue;
            }

            $type = $this->inferTypeFromExpression($expression, $name);

            $fields[] = [
                'name' => $name,
                'type' => $type,
            ];
        }

        return $fields;
    }

    /**
     * Infer the field type from the value expression.
     */
    protected function inferTypeFromExpression(string $expression, string $name): string
    {
        // $this->id or (int) cast
        if (preg_match('/\$this->id\b/', $expression) || str_contains($expression, '(int)')) {
            return 'integer';
        }

        // ->format() typically used on dates
        if (str_contains($expression, '->format(')) {
            return 'string';
        }

        // ->map() or ->pluck() indicates array/collection
        if (str_contains($expression, '->map(') || str_contains($expression, '->pluck(')) {
            return 'array';
        }

        // new XxxResource or XxxResource::collection
        if (preg_match('/Resource/', $expression)) {
            return 'object';
        }

        // (bool) or boolean cast
        if (str_contains($expression, '(bool)') || preg_match('/\bis_\w+/', $name)) {
            return 'boolean';
        }

        // (float) or (double)
        if (str_contains($expression, '(float)') || str_contains($expression, '(double)')) {
            return 'number';
        }

        // Numeric field names
        if (str_ends_with($name, '_id') || str_ends_with($name, '_count') || $name === 'id') {
            return 'integer';
        }

        if (str_ends_with($name, '_at') || str_ends_with($name, '_date')) {
            return 'string';
        }

        return 'string';
    }

    /**
     * Detect if the Resource uses a status/message wrapper pattern.
     * e.g. return ['status' => 200, 'message' => '...', 'data' => [...]]
     */
    protected function detectWrapper(string $content): bool
    {
        // Look for 'status' and 'message' keys in the toArray return
        $pattern = '/function\s+toArray\s*\([^)]*\)[^{]*\{/s';
        if (! preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $startPos = $match[0][1] + strlen($match[0][0]) - 1;
        $methodBody = $this->extractBraceBlock($content, $startPos);

        return str_contains($methodBody, "'status'") && str_contains($methodBody, "'message'");
    }

    /**
     * Resolve a PSR-4 class name to a file path.
     */
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

    /**
     * Extract content within balanced braces.
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
     * Extract content within balanced square brackets.
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
}
