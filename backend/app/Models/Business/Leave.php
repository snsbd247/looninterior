<?php

namespace App\Models\Business;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    use LogsActivity;

    public const TYPES = ['casual', 'sick', 'annual', 'unpaid'];

    protected $table = 'leaves';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_date' => 'date:Y-m-d', 'to_date' => 'date:Y-m-d', 'days' => 'decimal:1'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}