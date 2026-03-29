<?php

namespace Rbr\LaravelApiDocs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiEndpointParameter extends Model
{
    protected $fillable = [
        'api_endpoint_id',
        'name',
        'location',
        'type',
        'required',
        'description',
        'rules',
        'example',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id');
    }
}
