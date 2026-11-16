<?php

namespace App\Models\Concerns;

use App\Models\Business\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * For business records that belong to one branch (table has `branch_id`).
 *
 * - Reads are limited to the request's branch context (or all allowed branches in "all" mode).
 * - New records get the current branch automatically; in "all" mode the caller must set branch_id,
 *   and it must be one of the user's allowed branches.
 *
 * Outside an HTTP branch context (console, seeders) no filter is applied.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $q) {
            if (BranchContext::resolved()) {
                $q->whereIn($q->getModel()->qualifyColumn('branch_id'), BranchContext::scopeIds());
            }
        });

        static::creating(function ($model) {
            if (! BranchContext::resolved()) {
                return;
            }
            if (! $model->branch_id) {
                $model->branch_id = BranchContext::forWrite();
            }
            if (! $model->branch_id || ! in_array((int) $model->branch_id, BranchContext::allowed(), true)) {
                throw ValidationException::withMessages(['branch_id' => 'Please select a branch you have access to.']);
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
