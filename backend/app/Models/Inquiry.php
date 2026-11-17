<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['ip'];

    /** details: the cost-calculator estimate (type "calculator"), null for other forms. */
    protected $casts = ['details' => 'array'];
}
