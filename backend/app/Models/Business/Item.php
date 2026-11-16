<?php

namespace App\Models\Business;

use App\Models\BaseModel;

/** Material / product. Company-wide; stock is per store (stock_balances). */
class Item extends BaseModel
{
    protected $table = 'items';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_stock' => 'decimal:3', 'avg_cost' => 'decimal:4'];
    }

    public function setMinStockAttribute($v): void
    {
        $this->attributes['min_stock'] = $v === null || $v === '' ? 0 : $v;
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }
}