<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

class RequestValidationParser
{
    public function parse(string $formRequestClass): array
    {
        if (! class_exists($formRequestClass)) {
            return [];
        }

        try {
            $instance = new $formRequestClass;
            $rules = method_exists($instance, 'rules') ? $instance->rules() : [];
        } catch (\Throwable) {
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
                'rules' => implode(', ', $ruleStrings),
                'example' => $this->generateExample($field, $ruleStrings),
            ];
        }

        return $parameters;
    }

    protected function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $normalized[] = $rule;
            } elseif (is_object($rule)) {
                $normalized[] = class_basename($rule);
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

        // Use field name to generate a sensible default
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
