<?php

namespace Rbr\LaravelApiDocs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'project_path' => ['nullable', 'string', 'max:500'],
            'exclude_prefixes' => ['nullable', 'array'],
            'exclude_prefixes.*' => ['string', 'max:100'],
        ];
    }
}
