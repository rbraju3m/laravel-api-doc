<?php

namespace Rbr\LaravelApiDocs\Services\ApiDoc;

class ResponseGenerator
{
    public function generate(string $httpMethod, bool $hasValidation, bool $isAuthenticated): array
    {
        $responses = [];

        // Success response based on method
        $responses[] = match ($httpMethod) {
            'POST' => [
                'status_code' => 201,
                'description' => 'Created',
                'content_type' => 'application/json',
                'example_body' => ['message' => 'Resource created successfully.'],
            ],
            'DELETE' => [
                'status_code' => 204,
                'description' => 'No Content',
                'content_type' => 'application/json',
                'example_body' => null,
            ],
            default => [
                'status_code' => 200,
                'description' => 'OK',
                'content_type' => 'application/json',
                'example_body' => ['data' => '...'],
            ],
        };

        // 422 if endpoint has validation
        if ($hasValidation) {
            $responses[] = [
                'status_code' => 422,
                'description' => 'Validation Error',
                'content_type' => 'application/json',
                'example_body' => [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'field' => ['The field is required.'],
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

        return $responses;
    }
}
