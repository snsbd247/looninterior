<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

/** Planned installments of a project (e.g. 50 / 30 / 20 %). Each can be turned into an invoice. */
class PaymentSchedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['percent' => 'decimal:2', 'amount' => 'decimal:2', 'due_date' => 'date:Y-m-d'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}