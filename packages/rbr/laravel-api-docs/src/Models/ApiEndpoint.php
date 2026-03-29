<?php

namespace Rbr\LaravelApiDocs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiEndpoint extends Model
{
    protected $fillable = [
        'api_endpoint_group_id',
        'http_method',
        'uri',
        'name',
        'controller_class',
        'controller_method',
        'description',
        'middleware',
        'is_authenticated',
        'is_closure',
    ];

    protected function casts(): array
    {
        return [
            'middleware' => 'array',
            'is_authenticated' => 'boolean',
            'is_closure' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ApiEndpointGroup::class, 'api_endpoint_group_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ApiEndpointParameter::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ApiEndpointResponse::class);
    }
}
