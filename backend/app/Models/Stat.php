<?php

namespace App\Models;

class Stat extends BaseModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'value' => 'integer'];
    }
}
