<?php

namespace Rbr\LaravelApiDocs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ApiProject extends Model
{
    protected $fillable = ['name', 'base_url', 'project_path', 'description', 'is_external', 'sort_order', 'exclude_prefixes'];

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
            'exclude_prefixes' => 'array',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ApiEndpointGroup::class);
    }

    public function endpoints(): HasManyThrough
    {
        return $this->hasManyThrough(ApiEndpoint::class, ApiEndpointGroup::class);
    }
}
