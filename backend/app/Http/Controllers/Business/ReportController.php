<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Branch;
use App\Models\Business\Invoice;
use App\Models\Business\Payment;
use App\Models\Business\Transaction;
use App\Models\Business\WorkProject;
use App\Support\BranchContext;
use App\Support\Csv;
use Illuminate\Http\Request;

/**
 * Accounts reports. Every query is branch-scoped by the BelongsToBranch trait:
 * one branch, or all allowed branches in "All branches (central)" mode.
 * Add ?format=csv to download for Excel.
 */
class ReportController extends Controller
{
    private function range(Request $request): array
    {
        return [
            $request->query('from') ?: now()->startOfMonth()->toDateString(),
            $request->query('to') ?: now()->toDateString(),
        ];
    }

    /** Day book: every active entry in the period, all accounts, with opening/closing cash position. */
    public function cashbook(Request $request)
    {
        [$from, $to] = $this->range($request);
        $accounts = Account::query()->when($request->filled('account_id'), fn ($q) => $q->where('id', $request->query('account_id')))->get();
        $before = date('Y-m-d', strtotime($from.' -1 day'));
        $opening = round($accounts->sum(fn ($a) => $a->balance($before)), 2);

        $rows = Transaction::where('status', 'active')->whereIn('account_id', $accounts->pluck('id'))
            ->whereBetween('date', [$from, $to])->with(['account:id,name', 'category:id,name', 'project:id,code'])
            ->orderBy('date')->orderBy('id')->get();
        // Transfers between the listed accounts cancel out; keep them visible but they do not change the total.
        $running = $opening;
        $lines = $rows->map(function ($t) use (&$running) {
            $in = in_array($t->type, ['income', 'transfer_in'], true) ? (float) $t->amount : 0;
            $out = in_array($t->type, ['expense', 'transfer_out'], true) ? (float) $t->amount : 0;
            $running = round($running + $in - $out, 2);

            return ['date' => $t->date->toDateString(), 'type' => $t->type, 'account' => $t->account?->name, 'category' => $t->category?->name,
                'description' => $t->description, 'reference' => $t->reference, 'project' => $t->project?->code, 'in' => $in, 'out' => $out, 'balance' => $running];
        });

        if ($request->query('format') === 'csv') {
            return Csv::download("cashbook-{$from}-{$to}.csv", ['Date', 'Account', 'Category', 'Description', 'Reference', 'Project', 'In', 'Out', 'Balance'],
                array_merge([[$from, '', '', 'Opening balance', '', '', '', '', $opening]],
                    $lines->map(fn ($l) => [$l['date'], $l['account'], $l['category'] ?? str_replace('_', ' ', $l['type']), $l['description'], $l['reference'], $l['project'], $l['in'] ?: '', $l['out'] ?: '', $l['balance']])->all(),
                    [[$to, '', '', 'Closing balance', '', '', $lines->sum('in'), $lines->sum('out'), $running]]));
        }

        return response()->json(['from' => $from, 'to' => $to, 'opening' => $opening, 'closing' => $running,
            'total_in' => round($lines->sum('in'), 2), 'total_out' => round($lines->sum('out'), 2), 'lines' => $lines]);
    }

    /** Income & expense by category for the period (transfers excluded). */
    public function summary(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = Transaction::query()->where('transactions.status', 'active')->whereIn('transactions.type', ['income', 'expense'])
            ->whereBetween('transactions.date', [$from, $to])
            ->leftJoin('transaction_categories as c', 'c.id', '=', 'transactions.category_id')
            ->groupBy('transactions.type', 'c.name')
            ->selectRaw('transactions.type, COALESCE(c.name, "Uncategorised") as category, SUM(transactions.amount) as amount, COUNT(*) as entries')
            ->orderBy('transactions.type')->orderByDesc('amount')->get();

        $income = round((float) $rows->where('type', 'income')->sum('amount'), 2);
        $expense = round((float) $rows->where('type', 'expense')->sum('amount'), 2);

        if ($request->query('format') === 'csv') {
            return Csv::download("income-expense-{$from}-{$to}.csv", ['Type', 'Category', 'Entries', 'Amount'],
                array_merge($rows->map(fn ($r) => [$r->type, $r->category, $r->entries, $r->amount])->all(),
                    [['', 'Total income', '', $income], ['', 'Total expense', '', $expense], ['', 'Net', '', round($income - $expense, 2)]]));
        }

        return response()->json(['from' => $from, 'to' => $to, 'rows' => $rows, 'income' => $income, 'expense' => $expense, 'net' => round($income - $expense, 2)]);
    }

    /** Month-by-month profit & loss for a year: billed sales, collections, expenses, net cash. */
    public function profitLoss(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $start = sprintf('%04d-%02d-01', $year, $m);
            $end = date('Y-m-t', strtotime($start));
            $sales = (float) Invoice::where('status', '!=', 'draft')->where('status', '!=', 'cancelled')->whereBetween('date', [$start, $end])->sum('total');
            $income = (float) Transaction::where('status', 'active')->where('type', 'income')->whereBetween('date', [$start, $end])->sum('amount');
            $expense = (float) Transaction::where('status', 'active')->where('type', 'expense')->whereBetween('date', [$start, $end])->sum('amount');
            $months[] = ['month' => date('M', strtotime($start)), 'sales' => round($sales, 2), 'income' => round($income, 2), 'expense' => round($expense, 2), 'net' => round($income - $expense, 2)];
        }
        $totals = collect(['sales', 'income', 'expense', 'net'])->mapWithKeys(fn ($k) => [$k => round(collect($months)->sum($k), 2)]);

        if ($request->query('format') === 'csv') {
            return Csv::download("profit-loss-{$year}.csv", ['Month', 'Sales (invoiced)', 'Income (received)', 'Expense', 'Net'],
                array_merge(array_map(fn ($r) => array_values($r), $months), [['Total', $totals['sales'], $totals['income'], $totals['expense'], $totals['net']]]));
        }

        return response()->json(['year' => $year, 'months' => $months, 'totals' => $totals]);
    }

    /** Profitability of every project (contract vs costs, billed vs received). */
    public function projects(Request $request)
    {
        $rows = WorkProject::query()->with(['customer:id,name', 'branch:id,code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id')->limit(500)->get()
            ->map(fn ($p) => array_merge($p->only(['id', 'code', 'title', 'status', 'progress']), [
                'customer' => $p->customer?->name, 'branch' => $p->branch?->code,
            ], WorkProjectController::finance($p)));

        if ($request->query('format') === 'csv') {
            return Csv::download('project-profit.csv', ['Code', 'Title', 'Customer', 'Status', 'Contract', 'Invoiced', 'Received', 'Expenses', 'Materials', 'Cost', 'Profit'],
                $rows->map(fn ($r) => [$r['code'], $r['title'], $r['customer'], $r['status'], $r['contract'], $r['invoiced'], $r['received'], $r['expenses'], $r['materials'], $r['cost'], $r['profit']])->all());
        }

        return response()->json(['data' => $rows, 'totals' => collect(['contract', 'invoiced', 'received', 'cost', 'profit'])->mapWithKeys(fn ($k) => [$k => round($rows->sum($k), 2)])]);
    }

    /** Central view: side-by-side figures per branch for the period. */
    public function branches(Request $request)
    {
        [$from, $to] = $this->range($request);
        $ids = BranchContext::scopeIds();
        $rows = Branch::whereIn('id', $ids)->orderBy('id')->get()->map(function ($b) use ($from, $to) {
            $tx = fn ($type) => (float) Transaction::withoutGlobalScopes()->where('branch_id', $b->id)->where('status', 'active')->where('type', $type)->whereBetween('date', [$from, $to])->sum('amount');
            $sales = (float) Invoice::withoutGlobalScopes()->where('branch_id', $b->id)->whereNotIn('status', ['draft', 'cancelled'])->whereBetween('date', [$from, $to])->sum('total');
            $collected = (float) Payment::withoutGlobalScopes()->where('branch_id', $b->id)->where('status', 'received')->whereBetween('date', [$from, $to])->sum('amount');
            $cash = Account::withoutGlobalScopes()->where('branch_id', $b->id)->get()->sum(fn ($a) => $a->balance());

            return ['branch' => $b->name, 'code' => $b->code, 'sales' => round($sales, 2), 'collected' => round($collected, 2),
                'income' => round($tx('income'), 2), 'expense' => round($tx('expense'), 2), 'net' => round($tx('income') - $tx('expense'), 2), 'cash' => round($cash, 2)];
        });

        if ($request->query('format') === 'csv') {
            return Csv::download("branches-{$from}-{$to}.csv", ['Branch', 'Code', 'Sales', 'Collected', 'Income', 'Expense', 'Net', 'Cash & bank now'],
                $rows->map(fn ($r) => array_values($r))->all());
        }

        return response()->json(['from' => $from, 'to' => $to, 'data' => $rows]);
    }
}
