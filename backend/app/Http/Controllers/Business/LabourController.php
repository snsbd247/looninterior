<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Contractor;
use App\Models\Business\ContractorPayment;
use App\Models\Business\LabourEntry;
use App\Models\Business\TransactionCategory;
use App\Models\Business\WorkProject;
use App\Services\DocumentNumber;
use App\Services\Ledger;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Labour work on projects (cost goes to the project) and what each contractor is owed.
 * Paying a contractor is an expense in the ledger WITHOUT a project link, because the
 * project already carries the labour cost through the labour entries (no double counting).
 */
class LabourController extends Controller
{
    public function entries(Request $request)
    {
        $q = LabourEntry::query()->with(['project:id,code,title', 'contractor:id,name,trade', 'branch:id,code'])
            ->when($request->filled('work_project_id'), fn ($x) => $x->where('work_project_id', $request->query('work_project_id')))
            ->when($request->filled('contractor_id'), fn ($x) => $x->where('contractor_id', $request->query('contractor_id')))
            ->when($request->filled('status'), fn ($x) => $x->where('status', $request->query('status')))
            ->latest('date')->latest('id');
        $total = (float) (clone $q)->where('status', 'active')->sum('amount');

        return response()->json(array_merge($q->paginate(min(100, max(1, (int) $request->query('per_page', 25))))->toArray(), ['amount_total' => round($total, 2)]));
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'work_project_id' => 'required|integer|exists:work_projects,id',
            'contractor_id' => 'nullable|integer|exists:contractors,id',
            'date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|max:255',
            'workers' => 'required|integer|min:1|max:500',
            'days' => 'required|numeric|min:0.5|max:366',
            'rate' => 'required|numeric|min:0|max:99999999',
        ]);
        $project = WorkProject::findOrFail($data['work_project_id']); // branch-scoped
        $entry = DB::transaction(fn () => LabourEntry::create($data + [
            'branch_id' => $project->branch_id,
            'number' => DocumentNumber::next('LB', $project->branch_id),
            'amount' => round($data['workers'] * $data['days'] * $data['rate'], 2),
            'status' => 'active',
            'created_by' => Auth::id(),
        ]));

        return response()->json(['message' => "Labour {$entry->number} recorded", 'data' => $entry], 201);
    }

    public function cancelEntry(Request $request, LabourEntry $entry)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        if ($entry->status === 'cancelled') {
            throw ValidationException::withMessages(['reason' => 'Already cancelled.']);
        }
        $entry->update(['status' => 'cancelled', 'cancel_reason' => $data['reason']]);

        return response()->json(['message' => 'Labour entry cancelled']);
    }

    /** Contractors with work done, paid and due (current branch scope). */
    public function contractors(Request $request)
    {
        $work = LabourEntry::where('status', 'active')->whereNotNull('contractor_id')->groupBy('contractor_id')->selectRaw('contractor_id, SUM(amount) t')->pluck('t', 'contractor_id');
        $paid = ContractorPayment::where('status', 'paid')->groupBy('contractor_id')->selectRaw('contractor_id, SUM(amount) t')->pluck('t', 'contractor_id');
        $rows = Contractor::query()->when(! $request->boolean('inactive'), fn ($q) => $q->where('is_active', true))->orderBy('name')->get()
            ->map(fn ($c) => array_merge($c->only(['id', 'name', 'trade', 'phone', 'daily_rate', 'is_active']), [
                'work' => round((float) ($work[$c->id] ?? 0), 2), 'paid' => round((float) ($paid[$c->id] ?? 0), 2),
                'due' => round((float) $c->opening_due + (float) ($work[$c->id] ?? 0) - (float) ($paid[$c->id] ?? 0), 2),
            ]));

        return response()->json(['data' => $rows->values(), 'total_due' => round($rows->sum('due'), 2)]);
    }

    public function showContractor(Contractor $contractor)
    {
        $entries = LabourEntry::where('contractor_id', $contractor->id)->with('project:id,code')->orderBy('date')->get();
        $payments = ContractorPayment::where('contractor_id', $contractor->id)->with('account:id,name')->orderBy('date')->get();
        $lines = collect()
            ->concat($entries->map(fn ($e) => ['date' => $e->date->toDateString(), 'kind' => 'work', 'id' => $e->id, 'number' => $e->number, 'status' => $e->status,
                'detail' => "{$e->description} · {$e->project?->code} ({$e->workers}×{$e->days}d)", 'debit' => $e->status === 'active' ? (float) $e->amount : 0, 'credit' => 0]))
            ->concat($payments->map(fn ($p) => ['date' => $p->date->toDateString(), 'kind' => 'payment', 'id' => $p->id, 'number' => $p->number, 'status' => $p->status,
                'detail' => strtoupper($p->method).' from '.$p->account?->name, 'debit' => 0, 'credit' => $p->status === 'paid' ? (float) $p->amount : 0]))
            ->sortBy([['date', 'asc'], ['kind', 'desc']])->values();
        $running = (float) $contractor->opening_due;
        $lines = $lines->map(function ($l) use (&$running) {
            $running = round($running + $l['debit'] - $l['credit'], 2);

            return $l + ['balance' => $running];
        });

        return response()->json(['data' => $contractor, 'lines' => $lines, 'due' => $running]);
    }

    public function pay(Request $request, Contractor $contractor)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|integer|exists:accounts,id',
            'method' => 'required|string|max:20',
            'reference' => 'nullable|string|max:100',
            'date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:255',
        ]);
        $payment = DB::transaction(function () use ($data, $contractor) {
            $account = Account::lockForUpdate()->findOrFail($data['account_id']); // branch-scoped
            if ($account->balance() < (float) $data['amount']) {
                throw ValidationException::withMessages(['amount' => "Not enough balance in {$account->name} (".Money::format($account->balance()).').']);
            }
            $p = ContractorPayment::create($data + [
                'branch_id' => $account->branch_id, 'number' => DocumentNumber::next('CP', $account->branch_id),
                'contractor_id' => $contractor->id, 'status' => 'paid', 'created_by' => Auth::id(),
            ]);
            Ledger::post(['type' => 'expense', 'account_id' => $account->id, 'amount' => $p->amount, 'date' => $data['date'],
                'category_id' => TransactionCategory::idFor('labour'), 'description' => "Contractor payment {$p->number} to {$contractor->name}",
                'reference' => $data['reference'] ?? null], $p, $contractor);

            return $p;
        });

        return response()->json(['message' => "Payment {$payment->number} recorded", 'data' => $payment], 201);
    }

    public function cancelPayment(Request $request, ContractorPayment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        DB::transaction(function () use ($payment, $data) {
            $p = ContractorPayment::lockForUpdate()->findOrFail($payment->id);
            if ($p->status === 'cancelled') {
                return;
            }
            $p->update(['status' => 'cancelled', 'cancel_reason' => $data['reason']]);
            Ledger::cancelFor($p, 'Contractor payment cancelled: '.$data['reason']);
        });

        return response()->json(['message' => 'Payment cancelled']);
    }
}
