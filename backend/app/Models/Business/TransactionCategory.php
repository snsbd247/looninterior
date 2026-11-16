<?php

namespace App\Models\Business;

use App\Models\BaseModel;

/** Income / expense head. System categories (with slug) are used by automatic postings. */
class TransactionCategory extends BaseModel
{
    protected $table = 'transaction_categories';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public static function idFor(string $slug): ?int
    {
        return static::where('slug', $slug)->value('id');
    }
}