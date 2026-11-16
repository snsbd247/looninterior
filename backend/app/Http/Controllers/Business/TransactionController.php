<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Transaction;
use App\Models\Business\TransactionCategory;
use App\Models\Business\WorkProject;
use App\Services\Ledger;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $q = $this->filtered($request)->with(['account:id,name', 'category:id,name', 'project:id,code', 'creator:id,name', 'branch:id,code']);

        if ($request->query('format') === 'csv') {
            $rows = $q->orderBy('date')->orderBy('id')->limit(20000)->get();

            return Csv::download('transactions.csv', ['Date', 'Type', 'Account', 'Category', 'Description', 'Reference', 'Project', 'Amount', 'Status', 'Branch'],
                $rows->map(fn ($t) => [$t->date?->toDateString(), $t->type, $t->account?->name, $t->category?->name, $t->description, $t->reference, $t->project?->code, $t->amount, $t->status, $t->branch?->code])->all());
        }

        $sum = fn ($types) => (float) (clone $this->filtered($request))->where('status', 'active')->whereIn('type', $types)->sum('amount');

        return response()->json(array_merge(
            $q->latest('date')->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 25))))->toArray(),
            ['summary' => ['income' => round($sum(['income']), 2), 'expense' => round($sum(['expense']), 2)]]
        ));
    }

    private function filtered(Request $request)
    {
        $q = Transaction::query();
        foreach (['type', 'account_id', 'category_id', 'work_project_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        $q->where('status', $request->query('status', 'active'));
        if ($request->filled('from')) {
            $q->where('date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $q->where('date', '<=', $request->query('to'));
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('description', 'like', "%{$s}%")->orWhere('reference', 'like', "%{$s}%"));
        }

        return $q;
    }

    /** Manual income or expense (with optional receipt photo). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'account_id' => 'required|integer|exists:accounts,id',
            'category_id' => 'required|integer|exists:transaction_categories,id',
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'date' => 'required|date|before_or_equal:today',
            'work_project_id' => 'nullable|integer|exists:work_projects,id',
            'description' => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|max:8192|mimes:jpg,jpeg,png,webp,pdf',
        ]);
        $account = Account::findOrFail($data['account_id']); // branch-scoped
        $category = TransactionCategory::findOrFail($data['category_id']);
        if ($category->type !== $data['type'] || ! $category->is_active) {
            throw ValidationException::withMessages(['category_id' => 'Choose an active '.$data['type'].' category.']);
        }
        if (in_array($category->slug, ['customer_payment', 'supplier_payment'], true)) {
            throw ValidationException::withMessages(['category_id' => 'This category is posted automatically from invoices/purchases. Choose another one.']);
        }
        if (! empty($data['work_project_id'])) {
            $p = WorkProject::findOrFail($data['work_project_id']);
            if ((int) $p->branch_id !== (int) $account->branch_id) {
                throw ValidationException::withMessages(['work_project_id' => 'The project belongs to another branch.']);
            }
        }
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = date('Ymd').'-'.Str::random(20).'.'.strtolower($file->guessExtension() ?: 'bin');
            $file->move(storage_path('app/receipts'), $name);
            $data['attachment'] = 'receipts/'.$name;
        }

        $t = DB::transaction(fn () => Ledger::post($data));

        return response()->json(['message' => ucfirst($data['type']).' recorded', 'data' => $t], 201);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_account_id' => 'required|integer|exists:accounts,id',
            'to_account_id' => 'required|integer|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:100',
        ]);
        // Both accounts must be visible to the user (branch scope; cross-branch needs both branches or "all").
        $from = Account::withoutGlobalScopes()->findOrFail($data['from_account_id']);
        $to = Account::withoutGlobalScopes()->findOrFail($data['to_account_id']);
        foreach ([$from, $to] as $a) {
            if (! in_array((int) $a->branch_id, \App\Support\BranchContext::allowed(), true)) {
                throw ValidationException::withMessages(['to_account_id' => 'You do not have access to one of these accounts.']);
            }
        }
        if ($from->balance() < (float) $data['amount']) {
            throw ValidationException::withMessages(['amount' => 'Not enough balance in '.$from->name.' ('.$from->balance().').']);
        }
        [$out, $in] = Ledger::transfer($from->id, $to->id, $data['amount'], $data['date'], $data['note'] ?? null, $data['reference'] ?? null);

        return response()->json(['message' => 'Transfer recorded', 'data' => [$out, $in]], 201);
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        if ($transaction->source_type) {
            throw ValidationException::withMessages(['reason' => 'This entry was created by a payment/purchase. Cancel that document instead.']);
        }
        if ($transaction->status === 'cancelled') {
            throw ValidationException::withMessages(['reason' => 'Already cancelled.']);
        }
        DB::transaction(fn () => Ledger::cancel($transaction, $data['reason']));

        return response()->json(['message' => 'Entry cancelled']);
    }

    public function attachment(Transaction $transaction)
    {
        abort_if(! $transaction->attachment || str_contains($transaction->attachment, '..'), 404);
        $path = storage_path('app/'.$transaction->attachment);
        abort_unless(is_file($path), 404);

        return response()->file($path);
    }

    public function categories()
    {
        return response()->json(['data' => TransactionCategory::where('is_active', true)->orderBy('type')->orderBy('name')->get(['id', 'type', 'name', 'slug', 'is_system'])]);
    }
}
