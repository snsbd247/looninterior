<?php

namespace App\Models;

class Service extends BaseModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_featured' => 'boolean'];
    }
}
