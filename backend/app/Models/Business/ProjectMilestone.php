<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class ProjectMilestone extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d'];
    }

    public function project()
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }
}