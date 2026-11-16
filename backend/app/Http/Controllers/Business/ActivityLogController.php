<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\ActivityLog;
use App\Support\BranchContext;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $q = ActivityLog::query()
            ->with(['user:id,name,email', 'branch:id,name,code'])
            ->orderByDesc('id');

        // Branch view: that branch's entries plus global ones (settings, users…). "All": everything.
        if (! BranchContext::isAll()) {
            $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', BranchContext::current()));
        }

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->query('user_id'));
        }
        if ($request->filled('action')) {
            $q->where('action', $request->query('action'));
        }
        if ($request->filled('subject_type')) {
            $q->where('subject_type', $request->query('subject_type'));
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->date('to')->endOfDay());
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where('description', 'like', "%{$s}%");
        }

        return response()->json(array_merge(
            $q->paginate(min(100, max(1, (int) $request->query('per_page', 30))))->toArray(),
            ['subject_types' => ActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')]
        ));
    }
}
