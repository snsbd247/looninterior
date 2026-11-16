<?php

namespace App\Models\Business;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    public const TYPES = ['call', 'meeting', 'visit', 'note', 'follow_up', 'stage'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['follow_up_at' => 'datetime', 'done_at' => 'datetime'];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
