<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}