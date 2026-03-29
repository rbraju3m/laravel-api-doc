<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionMethod;
use ReflectionNamedType;

class ControllerParser
{
    public function parse(string $controllerClass, string $method): array
    {
        $result = [
            'description' => null,
            'form_request_class' => null,
            'return_type' => null,
            'uri_parameters' => [],
        ];

        if (! class_exists($controllerClass) || ! method_exists($controllerClass, $method)) {
            return $result;
        }

        $reflection = new ReflectionMethod($controllerClass, $method);

        // Extract docblock description
        $docComment = $reflection->getDocComment();
        if ($docComment) {
            $result['description'] = $this->parseDocblock($docComment);
        }

        // Detect FormRequest type-hints and URI parameters
        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $typeName = $type->getName();

                if (is_subclass_of($typeName, FormRequest::class)) {
                    $result['form_request_class'] = $typeName;
                }
            }

            // Check for URI parameters (primitive type-hints like int, string)
            if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                $result['uri_parameters'][] = [
                    'name' => $param->getName(),
                    'type' => $type->getName(),
                ];
            }

            // Model route binding (non-builtin, non-FormRequest)
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $typeName = $type->getName();
                if (! is_subclass_of($typeName, FormRequest::class) && $typeName !== 'Illuminate\Http\Request') {
                    $result['uri_parameters'][] = [
                        'name' => $param->getName(),
                        'type' => 'integer',
                    ];
                }
            }
        }

        // Return type
        $returnType = $reflection->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            $result['return_type'] = $returnType->getName();
        }

        return $result;
    }

    protected function parseDocblock(string $docComment): ?string
    {
        $lines = explode("\n", $docComment);
        $description = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B/*");

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
}
