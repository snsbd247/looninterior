<?php

namespace App\Models\Business;

use App\Models\BaseModel;

/** Company-wide labour contractor / daily worker group. */
class Contractor extends BaseModel
{
    protected $table = 'contractors';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'daily_rate' => 'decimal:2', 'opening_due' => 'decimal:2'];
    }

    public function setDailyRateAttribute($v): void
    {
        $this->attributes['daily_rate'] = $v === null || $v === '' ? 0 : $v;
    }

    public function setOpeningDueAttribute($v): void
    {
        $this->attributes['opening_due'] = $v === null || $v === '' ? 0 : $v;
    }
}