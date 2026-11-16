<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Purchase;
use App\Models\Business\Supplier;
use App\Models\Business\SupplierPayment;
use App\Services\Purchasing;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /** Suppliers with their due (purchases − payments in the current branch scope + opening due). */
    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->when(trim((string) $request->query('q')) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('phone', 'like', '%'.$request->query('q').'%')))
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get();
        $purchased = Purchase::where('status', 'received')->whereNotNull('supplier_id')->groupBy('supplier_id')->selectRaw('supplier_id, SUM(total) t')->pluck('t', 'supplier_id');
        $paid = SupplierPayment::where('status', 'paid')->whereNotNull('supplier_id')->groupBy('supplier_id')->selectRaw('supplier_id, SUM(amount) t')->pluck('t', 'supplier_id');

        $rows = $suppliers->map(fn ($s) => array_merge($s->only(['id', 'name', 'phone', 'email', 'address', 'is_active']), [
            'opening_due' => (float) $s->opening_due,
            'purchased' => round((float) ($purchased[$s->id] ?? 0), 2),
            'paid' => round((float) ($paid[$s->id] ?? 0), 2),
            'due' => round((float) $s->opening_due + (float) ($purchased[$s->id] ?? 0) - (float) ($paid[$s->id] ?? 0), 2),
        ]));

        return response()->json(['data' => $rows->values(), 'total_due' => round($rows->sum('due'), 2)]);
    }

    /** Statement: purchases and payments in date order with running due. */
    public function show(Supplier $supplier)
    {
        $purchases = Purchase::where('supplier_id', $supplier->id)->with(['store:id,name', 'project:id,code'])->orderBy('date')->get();
        $payments = SupplierPayment::where('supplier_id', $supplier->id)->with('account:id,name')->orderBy('date')->get();
        $lines = collect()
            ->concat($purchases->map(fn ($p) => ['date' => $p->date->toDateString(), 'kind' => 'purchase', 'id' => $p->id, 'number' => $p->number, 'ref' => $p->supplier_ref, 'status' => $p->status, 'debit' => $p->status === 'received' ? (float) $p->total : 0, 'credit' => 0, 'detail' => $p->type === 'grn' ? 'Into '.$p->store?->name : 'Direct for '.$p->project?->code]))
            ->concat($payments->map(fn ($p) => ['date' => $p->date->toDateString(), 'kind' => 'payment', 'id' => $p->id, 'number' => $p->number, 'ref' => $p->reference, 'status' => $p->status, 'debit' => 0, 'credit' => $p->status === 'paid' ? (float) $p->amount : 0, 'detail' => strtoupper($p->method).' from '.$p->account?->name]))
            ->sortBy([['date', 'asc'], ['kind', 'desc']])->values();
        $running = (float) $supplier->opening_due;
        $lines = $lines->map(function ($l) use (&$running) {
            $running = round($running + $l['debit'] - $l['credit'], 2);

            return $l + ['balance' => $running];
        });

        return response()->json(['data' => $supplier, 'lines' => $lines, 'due' => $running,
            'unpaid_purchases' => $purchases->where('status', 'received')->filter(fn ($p) => (float) $p->total - (float) $p->paid > 0.004)->map(fn ($p) => ['id' => $p->id, 'number' => $p->number, 'left' => round((float) $p->total - (float) $p->paid, 2)])->values()]);
    }

    public function pay(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|integer|exists:accounts,id',
            'purchase_id' => 'nullable|integer|exists:purchases,id',
            'method' => 'required|string|max:20',
            'reference' => 'nullable|string|max:100',
            'date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);
        Account::findOrFail($data['account_id']); // branch-scoped
        if (! empty($data['purchase_id']) && (int) Purchase::findOrFail($data['purchase_id'])->supplier_id !== (int) $supplier->id) {
            abort(422, 'That purchase is from another supplier.');
        }
        $payment = Purchasing::paySupplier($data + ['supplier_id' => $supplier->id]);

        return response()->json(['message' => "Payment {$payment->number} recorded", 'data' => $payment], 201);
    }

    public function cancelPayment(Request $request, SupplierPayment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        Purchasing::cancelSupplierPayment($payment, $data['reason']);

        return response()->json(['message' => 'Payment cancelled']);
    }
}
