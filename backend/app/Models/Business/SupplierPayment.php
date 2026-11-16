<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date:Y-m-d', 'cancelled_at' => 'datetime'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}