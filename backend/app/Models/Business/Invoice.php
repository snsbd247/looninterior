<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToBranch, LogsActivity;

    public const STATUSES = ['draft', 'issued', 'partial', 'paid', 'cancelled'];

    protected $guarded = ['id'];

    protected $appends = ['due', 'is_overdue'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'discount_value' => 'decimal:2',
            'tax' => 'decimal:2', 'tax_rate' => 'decimal:3', 'total' => 'decimal:2',
            'paid' => 'decimal:2', 'credited' => 'decimal:2',
            'issued_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    /** Amount still to be received. */
    public function getDueAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return '0.00';
        }

        return number_format(max(0, (float) $this->total - (float) $this->paid - (float) $this->credited), 2, '.', '');
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['issued', 'partial'], true)
            && $this->due_date && $this->due_date->lt(today()) && (float) $this->due > 0;
    }

    /** Status from the money: issued → partial → paid. */
    public function refreshPaymentStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            return;
        }
        $due = (float) $this->total - (float) $this->paid - (float) $this->credited;
        $this->status = $due <= 0.004 ? 'paid' : ((float) $this->paid > 0 || (float) $this->credited > 0 ? 'partial' : 'issued');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function project()
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class)->latest('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}