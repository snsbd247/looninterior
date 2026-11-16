<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Marketing campaign: one SMS or e-mail message to a list of leads/customers of a branch. */
class Campaign extends Model
{
    use BelongsToBranch, LogsActivity;

    public const CHANNELS = ['sms', 'email'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'sent_at' => 'datetime'];
    }

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
