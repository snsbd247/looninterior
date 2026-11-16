<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    public const STATUSES = ['present', 'absent', 'half_day', 'leave', 'holiday'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}