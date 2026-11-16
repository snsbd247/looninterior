<?php

namespace App\Models;

class Testimonial extends BaseModel
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rating' => 'integer'];
    }
}
