<?php

namespace Rbr\LaravelApiDocs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiEndpointGroup extends Model
{
    protected $fillable = ['name', 'prefix', 'description', 'sort_order', 'api_project_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ApiProject::class, 'api_project_id');
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(ApiEndpoint::class);
    }
}
