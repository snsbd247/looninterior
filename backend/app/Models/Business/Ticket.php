<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use BelongsToBranch, LogsActivity;

    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const SOURCES = ['phone', 'website', 'email', 'walk_in', 'visit', 'portal'];

    protected $guarded = ['id'];

    protected $appends = ['in_warranty'];

    /** SMS to the customer when a ticket is opened (staff, website or client portal); only if enabled in Business Settings. */
    protected static function booted(): void
    {
        static::created(function (Ticket $t) {
            $c = $t->customer_id ? Customer::withoutGlobalScopes()->find($t->customer_id, ['id', 'name', 'phone']) : null;
            \App\Services\Sms::event('ticket', $t->contact_phone ?: $c?->phone, ['name' => $c?->name ?? $t->contact_name, 'ticket' => $t->number]);
        });
    }

    protected function casts(): array
    {
        return ['visit_at' => 'datetime', 'warranty_until' => 'date:Y-m-d', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function getInWarrantyAttribute(): ?bool
    {
        return $this->warranty_until ? $this->warranty_until->gte($this->created_at?->startOfDay() ?? today()) : null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function project()
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->latest('id');
    }
}
