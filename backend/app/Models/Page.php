<?php

namespace App\Models;

class Page extends BaseModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_in_footer' => 'boolean'];
    }
}
