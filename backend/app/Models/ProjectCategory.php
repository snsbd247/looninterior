<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectCategory extends BaseModel
{
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
