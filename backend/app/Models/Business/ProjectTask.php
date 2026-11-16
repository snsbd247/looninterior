<?php

namespace App\Models\Business;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    public const STATUSES = ['todo', 'doing', 'done'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d'];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function milestone()
    {
        return $this->belongsTo(ProjectMilestone::class);
    }
}