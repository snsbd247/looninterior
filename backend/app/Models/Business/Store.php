<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}