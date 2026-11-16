<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ContractorPayment extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date:Y-m-d'];
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}