<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['basic' => 'decimal:2', 'allowance' => 'decimal:2', 'deduction' => 'decimal:2', 'bonus' => 'decimal:2', 'advance' => 'decimal:2', 'net' => 'decimal:2',
            'present_days' => 'decimal:1', 'absent_days' => 'decimal:1', 'unpaid_leave_days' => 'decimal:1'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}