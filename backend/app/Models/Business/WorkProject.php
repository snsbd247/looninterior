<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** A running interior job (business). Not the website portfolio "projects". */
class WorkProject extends Model
{
    use BelongsToBranch, LogsActivity;

    public const STATUSES = ['planning', 'running', 'on_hold', 'completed', 'cancelled'];

    protected $table = 'work_projects';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d',
            'handover_date' => 'date:Y-m-d', 'warranty_until' => 'date:Y-m-d',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class)->latest('id');
    }

    public function files()
    {
        return $this->hasMany(ProjectFile::class)->latest('id');
    }

    public function schedules()
    {
        return $this->hasMany(PaymentSchedule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function portfolio()
    {
        return $this->belongsTo(Project::class, 'portfolio_project_id');
    }

    /** Recalculate progress as the average of milestone progress. */
    public function refreshProgress(): void
    {
        $avg = $this->milestones()->avg('progress');
        if ($avg !== null) {
            $this->forceFill(['progress' => (int) round($avg)])->saveQuietly();
        }
    }
}
