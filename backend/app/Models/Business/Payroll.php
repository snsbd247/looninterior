<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Monthly salary sheet of a branch. draft → finalized → paid (posted to the ledger as "Salary"). */
class Payroll extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'paid_date' => 'date:Y-m-d'];
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}