<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Labour work done on a project (adds to the project cost; the contractor is owed the amount). */
class LabourEntry extends Model
{
    use BelongsToBranch, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'days' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function project()
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}