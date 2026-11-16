<?php

namespace App\Models\Business;

use App\Models\BaseModel;

/** Company-wide supplier. Due = opening due + purchases − payments (in the user's branch scope). */
class Supplier extends BaseModel
{
    protected $table = 'suppliers';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'opening_due' => 'decimal:2'];
    }

    public function setOpeningDueAttribute($v): void
    {
        $this->attributes['opening_due'] = $v === null || $v === '' ? 0 : $v;
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function due(): float
    {
        $purchased = (float) Purchase::where('supplier_id', $this->id)->where('status', 'received')->sum('total');
        $paid = (float) SupplierPayment::where('supplier_id', $this->id)->where('status', 'paid')->sum('amount');

        return round((float) $this->opening_due + $purchased - $paid, 2);
    }
}