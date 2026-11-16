<?php

namespace App\Models\Business;

use App\Models\BaseModel;
use App\Models\User;
use App\Support\BusinessFoundation;

class Branch extends BaseModel
{
    protected $table = 'branches';

    protected static function booted(): void
    {
        // Every new branch starts with a Cash account.
        static::created(fn (Branch $b) => BusinessFoundation::ensureCashAccount($b));
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
