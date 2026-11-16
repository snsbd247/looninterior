<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['join_date' => 'date:Y-m-d', 'basic_salary' => 'decimal:2', 'allowance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class)->latest('from_date');
    }
}