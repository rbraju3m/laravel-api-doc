<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

class ExternalRequestParser
{
    /**
     * Parse validation rules from a FormRequest in an external project.
     */
    public function parse(string $projectPath, string $formRequestClass): array
    {
        $filePath = $this->resolveClassFile($projectPath, $formRequestClass);

        if (! $filePath || ! file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        $rules = $this->extractRules($content);

        if (empty($rules)) {
            return [];
        }

        $parameters = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;
            $ruleStrings = $this->normalizeRules($ruleList);

            $parameters[] = [
                'name' => $field,
                'location' => 'body',
                'type' => $this->detectType($ruleStrings),
                'required' => in_array('required', $ruleStrings),
                'description' => $this->generateDescription($field, $ruleStrings),
                'rules' => implode(', ', $ruleStrings),
                'example' => $this->generateExample($field, $ruleStrings),
            ];
        }

        return $parameters;
    }

    /**
     * Resolve a PSR-4 class name to a file path within the project.
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
     * Extract rules from the rules() method using balanced brace/bracket parsing.
     * Handles: return [...], $rules = [...]; return $rules;, and conditional rules.
     */
    protected function extractRules(string $content): array
    {
        // Find the rules() method opening brace
        $pattern = '/function\s+rules\s*\(\s*\)\s*(?::\s*array\s*)?\{/s';
        if (! preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $bracePos = strpos($content, '{', $match[0][1] + strlen('function'));
        $methodBody = $this->extractBraceBlock($content, $bracePos);

        if (! $methodBody) {
            return [];
        }

        // Collect all rules from ALL array literals in the method body.
        // This catches: return [...], $rules = [...], array_merge($rules, [...])
        $allRules = [];
        $this->findAllArrayRules($methodBody, $allRules);

        return $allRules;
    }

    /**
     * Find all rule arrays in the method body and merge them.
     */
    protected function findAllArrayRules(string $body, array &$rules): void
    {
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            // Find the next '[' that's part of a rules assignment
            // Look for patterns like: = [...], return [...], merge(..., [...])
            $bracketPos = strpos($body, '[', $offset);
            if ($bracketPos === false) {
                break;
            }

            // Check context: is this a rules array?
            $before = substr($body, max(0, $bracketPos - 80), min(80, $bracketPos));

            // Skip if it's inside a string or comment
            if ($this->isInsideString($body, $bracketPos)) {
                $offset = $bracketPos + 1;

                continue;
            }

            // Only parse arrays that look like rule definitions
            // (after =>, =, return, merge(, or at start of argument)
            $isRulesContext = preg_match('/(?:=\s*|return\s+|merge\s*\(\s*[^,]*,\s*)$/s', $before);

            if ($isRulesContext) {
                $arrayContent = $this->extractBracketBlock($body, $bracketPos);
                if ($arrayContent !== null) {
                    $this->parseFieldRules($arrayContent, $rules);
                    $offset = $bracketPos + strlen($arrayContent) + 2;

                    continue;
                }
            }

            $offset = $bracketPos + 1;
        }
    }

    /**
     * Parse 'field' => 'rules' entries from array content.
     */
    protected function parseFieldRules(string $arrayContent, array &$rules): void
    {
        // Match 'field_name' => 'rule|rule' or 'field_name' => ['rule', 'rule']
        // Also match 'field_name' => expression (ternary, variable, etc.)
        preg_match_all("/['\"](\w[\w.*]*?)['\"]\s*=>\s*(\[.*?\]|'[^']*'|\"[^\"]*\"|[^,\]\n]+)/s", $arrayContent, $fieldMatches, PREG_SET_ORDER);

        foreach ($fieldMatches as $match) {
            $field = $match[1];
            $rulesValue = trim($match[2]);

            if (str_starts_with($rulesValue, '[')) {
                // Array format: ['required', 'string', 'max:255']
                preg_match_all("/['\"]([^'\"]+)['\"]/", $rulesValue, $ruleItems);
                if (! empty($ruleItems[1])) {
                    $rules[$field] = $ruleItems[1];
                }
            } elseif (str_starts_with($rulesValue, "'") || str_starts_with($rulesValue, '"')) {
                // String format: 'required|string|max:255'
                $rulesStr = trim($rulesValue, "'\"");
                $rules[$field] = explode('|', $rulesStr);
            } elseif (str_contains($rulesValue, '?')) {
                // Ternary: $this->condition() ? 'required|string' : 'string'
                // Extract the first string literal as the rules
                if (preg_match("/['\"]([^'\"]+)['\"]/", $rulesValue, $ternaryMatch)) {
                    $rules[$field] = explode('|', $ternaryMatch[1]);
                }
            }
            // Skip complex expressions (function calls, variables, etc.)
        }
    }

    /**
     * Basic check if a position is inside a string literal.
     */
    protected function isInsideString(string $content, int $pos): bool
    {
        $before = substr($content, 0, $pos);
        $singleQuotes = substr_count($before, "'") - substr_count($before, "\\'");
        $doubleQuotes = substr_count($before, '"') - substr_count($before, '\\"');

        return ($singleQuotes % 2 !== 0) || ($doubleQuotes % 2 !== 0);
    }

    /**
     * Extract content within balanced square brackets (returns content without outer brackets).
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
     * Extract content within balanced braces (returns full block including braces).
     */
    protected function extractBraceBlock(string $content, int $startPos): ?string
    {
        if (! isset($content[$startPos]) || $content[$startPos] !== '{') {
            return null;
        }

        $depth = 0;
        $length = strlen($content);

        for ($i = $startPos; $i < $length; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $startPos + 1, $i - $startPos - 1);
                }
            }
        }

        return null;
    }

    protected function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $normalized[] = $rule;
            }
        }

        return $normalized;
    }

    protected function detectType(array $rules): string
    {
        $typeMap = [
            'integer' => 'integer',
            'numeric' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
            'file' => 'file',
            'image' => 'file',
            'email' => 'string (email)',
            'date' => 'string (date)',
            'url' => 'string (url)',
            'json' => 'string (json)',
            'string' => 'string',
        ];

        foreach ($typeMap as $rule => $type) {
            if (in_array($rule, $rules)) {
                return $type;
            }
        }

        return 'string';
    }

    protected function generateDescription(string $field, array $rules): string
    {
        $label = ucfirst(str_replace(['_', '-'], ' ', $field));
        $parts = [$label];

        // Add constraints from rules
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

    protected function generateExample(string $field, array $rules): string
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
        if (in_array('file', $rules) || in_array('image', $rules)) {
            return '(binary)';
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

        return 'example_value';
    }
}
