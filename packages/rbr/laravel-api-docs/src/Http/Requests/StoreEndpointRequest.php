<?php

namespace Rbr\LaravelApiDocs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_name' => ['required', 'string', 'max:255'],
            'http_method' => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD'],
            'uri' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_authenticated' => ['boolean'],
            'parameters' => ['nullable', 'array'],
            'parameters.*.name' => ['required', 'string', 'max:255'],
            'parameters.*.location' => ['required', 'in:query,body,uri'],
            'parameters.*.type' => ['required', 'string', 'max:50'],
            'parameters.*.required' => ['boolean'],
            'parameters.*.description' => ['nullable', 'string', 'max:500'],
            'parameters.*.rules' => ['nullable', 'string', 'max:500'],
            'parameters.*.example' => ['nullable', 'string', 'max:255'],
            'responses' => ['nullable', 'array'],
            'responses.*.status_code' => ['required', 'integer', 'min:100', 'max:599'],
            'responses.*.description' => ['nullable', 'string', 'max:500'],
            'responses.*.content_type' => ['nullable', 'string', 'max:100'],
            'responses.*.example_body' => ['nullable', 'string'],
        ];
    }
}
