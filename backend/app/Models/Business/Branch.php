<?php

namespace App\Models\Business;

use App\Models\BaseModel;
use App\Models\User;

class Branch extends BaseModel
{
    protected $table = 'branches';

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
