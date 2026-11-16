<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** Stock ledger row. Never edited or deleted; cancellations add "reverse" rows. */
class StockMovement extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_cost' => 'decimal:4', 'value' => 'decimal:2', 'date' => 'date:Y-m-d'];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function project()
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }

    public function source()
    {
        return $this->morphTo();
    }
}