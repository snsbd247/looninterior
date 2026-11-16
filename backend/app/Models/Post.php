<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Post extends BaseModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'published_at' => 'date:Y-m-d'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
