<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends BaseModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'deadline' => 'date:Y-m-d'];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
