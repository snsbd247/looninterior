<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Issue to project / return from project / store transfer / adjustment. */
class StockEntry extends Model
{
    use BelongsToBranch, LogsActivity;

    public const TYPES = ['issue', 'return', 'transfer', 'adjust'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'cancelled_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function project()
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}