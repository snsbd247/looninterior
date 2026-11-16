<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** GRN (goods into a store) or a direct purchase charged straight to a project. */
class Purchase extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'total' => 'decimal:2', 'paid' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function project()
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}