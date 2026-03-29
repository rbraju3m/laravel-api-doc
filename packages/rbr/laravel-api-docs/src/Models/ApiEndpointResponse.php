<?php

namespace Rbr\LaravelApiDocs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiEndpointResponse extends Model
{
    protected $fillable = [
        'api_endpoint_id',
        'status_code',
        'description',
        'content_type',
        'example_body',
    ];

    protected function casts(): array
    {
        return [
            'example_body' => 'array',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id');
    }
}
