<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Business customer (CRM). Not the website "clients" logo list.
 * Can sign in to the client portal (/portal) when staff enable it (portal_enabled + portal_password).
 */
class Customer extends Model
{
    use BelongsToBranch, HasApiTokens, LogsActivity;

    protected $guarded = ['id'];

    protected $hidden = ['portal_password'];

    protected $casts = ['portal_enabled' => 'boolean', 'portal_last_login' => 'datetime', 'marketing_opt_out' => 'boolean'];

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function projects()
    {
        return $this->hasMany(WorkProject::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
