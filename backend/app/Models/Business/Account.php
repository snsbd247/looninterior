<?php

namespace App\Models\Business;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Cash / bank / mobile-banking account of a branch. Balance is always computed from transactions. */
class Account extends Model
{
    use BelongsToBranch, LogsActivity;

    public const TYPES = ['cash', 'bank', 'mobile'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /** Balance up to and including $until (Y-m-d), or now. */
    public function balance(?string $until = null): float
    {
        $q = Transaction::withoutGlobalScopes()->where('account_id', $this->id)->where('status', 'active');
        if ($until) {
            $q->where('date', '<=', $until);
        }
        $in = (clone $q)->whereIn('type', ['income', 'transfer_in'])->sum('amount');
        $out = (clone $q)->whereIn('type', ['expense', 'transfer_out'])->sum('amount');

        return round((float) $this->opening_balance + (float) $in - (float) $out, 2);
    }
}