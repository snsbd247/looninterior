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
        // Every new branch starts with a Cash account and a Main Store.
        static::created(function (Branch $b) {
            BusinessFoundation::ensureCashAccount($b);
            BusinessFoundation::ensureStore($b);
        });
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
