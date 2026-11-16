<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['cv_path'];

    protected $appends = ['has_cv'];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function getHasCvAttribute(): bool
    {
        return ! empty($this->attributes['cv_path']);
    }
}
