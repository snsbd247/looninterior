<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use BelongsToBranch, LogsActivity;

    public const STAGES = ['new', 'contacted', 'site_visit', 'quotation_sent', 'negotiation', 'won', 'lost'];
    public const SOURCES = ['website', 'phone', 'facebook', 'instagram', 'whatsapp', 'referral', 'walk_in', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'won_at' => 'datetime',
            'marketing_opt_out' => 'boolean',
        ];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class)->latest('id');
    }
}
