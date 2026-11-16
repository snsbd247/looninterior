<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

/** One person in a campaign (snapshot of name and phone/e-mail when the campaign was started). */
class CampaignRecipient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
