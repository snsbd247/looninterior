<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

/** Current quantity of an item in a store (cache of stock_movements, updated in the same transaction). */
class StockBalance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}