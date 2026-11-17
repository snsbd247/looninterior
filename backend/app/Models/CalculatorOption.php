<?php

namespace App\Models;

/** One option of the website cost calculator (project type, style, quality or add-on). */
class CalculatorOption extends BaseModel
{
    public const GROUPS = ['type', 'style', 'quality', 'addon', 'room'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean', 'value' => 'float', 'default_qty' => 'integer'];
    }
}
