<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Branch;
use App\Models\Business\TaxRate;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Http\Request;

/** Small dropdown lists for business forms (any signed-in staff with a branch). */
class LookupController extends Controller
{
    /** Active staff who work in the current branch(es): for "assign to" dropdowns. */
    public function staff(Request $request)
    {
        $ids = BranchContext::scopeIds();
        $users = User::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $ids))
                ->orWhereHas('role', fn ($r) => $r->where('slug', 'admin')))
            ->orderBy('name')->get(['id', 'name', 'role_id'])->load('role:id,name');

        return response()->json(['data' => $users]);
    }

    public function taxRates()
    {
        return response()->json(['data' => TaxRate::where('is_active', true)->orderByDesc('is_default')->orderBy('id')->get(['id', 'name', 'rate', 'is_default'])]);
    }

    /** Branches the user may write to (for the branch picker in "all branches" mode). */
    public function branches()
    {
        return response()->json(['data' => Branch::whereIn('id', BranchContext::allowed())->orderBy('id')->get(['id', 'name', 'code'])]);
    }
}
