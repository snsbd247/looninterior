<?php

namespace App\Models\Business;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
