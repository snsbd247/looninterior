<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use BelongsToBranch, LogsActivity;

    public const STATUSES = ['draft', 'sent', 'approved', 'rejected', 'revised', 'cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'discount_value' => 'decimal:2',
            'tax' => 'decimal:2', 'tax_rate' => 'decimal:3', 'total' => 'decimal:2',
            'sent_at' => 'datetime', 'approved_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
