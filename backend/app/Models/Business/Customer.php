<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Business customer (CRM). Not the website "clients" logo list. */
class Customer extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

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
