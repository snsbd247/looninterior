<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'expected_date' => 'date:Y-m-d', 'total' => 'decimal:2'];
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}