<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Transaction;
use App\Support\BranchContext;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = Account::query()->with('branch:id,name,code')
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('branch_id')->orderBy('type')->orderBy('name')->get()
            ->map(fn (Account $a) => array_merge($a->toArray(), ['balance' => $a->balance()]));

        return response()->json(['data' => $accounts, 'total' => round($accounts->sum('balance'), 2)]);
    }

    /** Ledger of one account with opening balance and running balance. */
    public function show(Request $request, Account $account)
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->toDateString();
        $opening = $account->balance(date('Y-m-d', strtotime($from.' -1 day')));

        $rows = Transaction::where('account_id', $account->id)->where('status', 'active')
            ->whereBetween('date', [$from, $to])->with(['category:id,name', 'project:id,code', 'creator:id,name'])
            ->orderBy('date')->orderBy('id')->get();
        $running = $opening;
        $lines = $rows->map(function ($t) use (&$running) {
            $in = in_array($t->type, ['income', 'transfer_in'], true) ? (float) $t->amount : 0;
            $out = in_array($t->type, ['expense', 'transfer_out'], true) ? (float) $t->amount : 0;
            $running = round($running + $in - $out, 2);

            return array_merge($t->toArray(), ['in' => $in, 'out' => $out, 'balance' => $running]);
        });

        if ($request->query('format') === 'csv') {
            return Csv::download("ledger-{$account->name}-{$from}-{$to}.csv", ['Date', 'Description', 'Category', 'Reference', 'In', 'Out', 'Balance'],
                array_merge([[$from, 'Opening balance', '', '', '', '', $opening]],
                    $lines->map(fn ($l) => [$l['date'], $l['description'], $l['category']['name'] ?? str_replace('_', ' ', $l['type']), $l['reference'], $l['in'] ?: '', $l['out'] ?: '', $l['balance']])->all()));
        }

        return response()->json([
            'data' => array_merge($account->load('branch:id,name,code')->toArray(), ['balance' => $account->balance()]),
            'from' => $from, 'to' => $to, 'opening' => $opening, 'closing' => $running,
            'total_in' => round($lines->sum('in'), 2), 'total_out' => round($lines->sum('out'), 2),
            'lines' => $lines,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['branch_id'] = $data['branch_id'] ?? BranchContext::forWrite();
        $account = Account::create($data);

        return response()->json(['message' => 'Account created', 'data' => $account], 201);
    }

    public function update(Request $request, Account $account)
    {
        $data = $this->validated($request);
        unset($data['branch_id']);
        $account->update($data);

        return response()->json(['message' => 'Account updated', 'data' => $account]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(Account::TYPES)],
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:-999999999999|max:999999999999',
            'is_active' => 'boolean',
        ]);
    }
}
