<?php

namespace App\Models\Business;

use App\Models\BaseModel;

class TaxRate extends BaseModel
{
    protected $table = 'tax_rates';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean', 'rate' => 'decimal:3'];
    }

    protected static function booted(): void
    {
        // Only one default tax rate.
        static::saved(function (TaxRate $tax) {
            if ($tax->is_default) {
                static::whereKeyNot($tax->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }
}
