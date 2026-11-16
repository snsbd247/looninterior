<?php

namespace App\Services;

use App\Models\Business\Account;
use App\Models\Business\Item;
use App\Models\Business\Purchase;
use App\Models\Business\PurchaseOrder;
use App\Models\Business\PurchaseOrderItem;
use App\Models\Business\StockEntry;
use App\Models\Business\StockMovement;
use App\Models\Business\Store;
use App\Models\Business\Supplier;
use App\Models\Business\SupplierPayment;
use App\Models\Business\TransactionCategory;
use App\Models\Business\WorkProject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Purchases, stock entries and supplier payments: each in one DB transaction. */
class Purchasing
{
    /**
     * GRN (type grn: into a store, increases stock) or direct purchase (type direct: charged to a project).
     * $data: type, supplier_id?, store_id?, work_project_id?, purchase_order_id?, date, supplier_ref?, notes?,
     *        items[{item_id?, purchase_order_item_id?, description, unit?, qty, rate}],
     *        pay_now_account_id?, pay_method?  (pay immediately, e.g. cash purchase from market)
     */
    public static function createPurchase(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $store = $type === 'grn' ? Store::findOrFail($data['store_id']) : null;
            $project = $type === 'direct' ? WorkProject::findOrFail($data['work_project_id']) : null;
            $branchId = $store?->branch_id ?? $project->branch_id;
            $order = ! empty($data['purchase_order_id']) ? PurchaseOrder::lockForUpdate()->findOrFail($data['purchase_order_id']) : null;
            if ($order && (! in_array($order->status, ['sent', 'partial', 'draft'], true) || (int) $order->store_id !== (int) $store?->id)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'This purchase order cannot be received into this store.']);
            }
            if (empty($data['supplier_id']) && empty($data['pay_now_account_id'])) {
                throw ValidationException::withMessages(['supplier_id' => 'Choose a supplier, or pay now from an account.']);
            }

            $rows = [];
            $total = 0;
            foreach ($data['items'] as $row) {
                $qty = round((float) $row['qty'], 3);
                $rate = round((float) $row['rate'], 2);
                $amount = round($qty * $rate, 2);
                $item = ! empty($row['item_id']) ? Item::findOrFail($row['item_id']) : null;
                if ($type === 'grn' && ! $item) {
                    throw ValidationException::withMessages(['items' => 'Every line received into a store must be a stock item.']);
                }
                $rows[] = ['item' => $item, 'row' => [
                    'item_id' => $item?->id,
                    'purchase_order_item_id' => $row['purchase_order_item_id'] ?? null,
                    'description' => trim((string) ($row['description'] ?? '')) ?: (string) $item?->name,
                    'unit' => $row['unit'] ?? $item?->unit,
                    'qty' => $qty, 'rate' => $rate, 'amount' => $amount,
                ]];
                $total += $amount;
            }

            $purchase = Purchase::create([
                'branch_id' => $branchId,
                'number' => DocumentNumber::next($type === 'grn' ? 'GRN' : 'DP', $branchId),
                'type' => $type,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_order_id' => $order?->id,
                'store_id' => $store?->id,
                'work_project_id' => $project?->id,
                'supplier_ref' => $data['supplier_ref'] ?? null,
                'date' => $data['date'],
                'total' => round($total, 2),
                'status' => 'received',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($rows as ['item' => $item, 'row' => $row]) {
                $purchase->items()->create($row);
                if ($type === 'grn') {
                    Stock::receive($store, $item, $row['qty'], $row['rate'], $data['date'], $purchase, 'in', "GRN {$purchase->number}");
                }
                if ($order && $row['purchase_order_item_id']) {
                    $poi = PurchaseOrderItem::where('purchase_order_id', $order->id)->findOrFail($row['purchase_order_item_id']);
                    $poi->increment('received_qty', $row['qty']);
                }
            }
            if ($order) {
                $order->load('items');
                $complete = $order->items->every(fn ($i) => (float) $i->received_qty + 0.0005 >= (float) $i->qty);
                $order->update(['status' => $complete ? 'received' : 'partial']);
            }

            if (! empty($data['pay_now_account_id'])) {
                self::paySupplier([
                    'supplier_id' => $purchase->supplier_id, 'purchase_id' => $purchase->id, 'account_id' => $data['pay_now_account_id'],
                    'amount' => $purchase->total, 'method' => $data['pay_method'] ?? 'cash', 'date' => $data['date'],
                    'reference' => $data['supplier_ref'] ?? null, 'notes' => 'Paid on purchase',
                ], $branchId);
            }

            return $purchase->fresh();
        });
    }

    public static function cancelPurchase(Purchase $purchase, string $reason): void
    {
        DB::transaction(function () use ($purchase, $reason) {
            $p = Purchase::lockForUpdate()->findOrFail($purchase->id);
            if ($p->status === 'cancelled') {
                return;
            }
            if (SupplierPayment::where('purchase_id', $p->id)->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages(['reason' => 'Cancel the payments made against this purchase first.']);
            }
            if ($p->type === 'grn') {
                $store = Store::withoutGlobalScopes()->findOrFail($p->store_id);
                foreach ($p->items as $line) {
                    Stock::unreceive($store, Item::findOrFail($line->item_id), (float) $line->qty, (float) $line->rate, now()->toDateString(), $p);
                    if ($line->purchase_order_item_id) {
                        PurchaseOrderItem::whereKey($line->purchase_order_item_id)->decrement('received_qty', $line->qty);
                    }
                }
                if ($p->purchase_order_id) {
                    $order = PurchaseOrder::with('items')->find($p->purchase_order_id);
                    $order?->update(['status' => $order->items->sum('received_qty') > 0 ? 'partial' : 'sent']);
                }
            }
            $p->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        });
    }

    /** issue / return / transfer / adjust. $data: type, store_id, to_store_id?, work_project_id?, date, notes?, items[{item_id, qty}] */
    public static function createEntry(array $data): StockEntry
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $store = Store::findOrFail($data['store_id']);
            $to = $type === 'transfer' ? Store::withoutGlobalScopes()->findOrFail($data['to_store_id']) : null;
            $project = in_array($type, ['issue', 'return'], true) ? WorkProject::findOrFail($data['work_project_id']) : null;
            if ($project && (int) $project->branch_id !== (int) $store->branch_id) {
                throw ValidationException::withMessages(['work_project_id' => 'The project belongs to another branch than this store.']);
            }
            if ($to && (int) $to->id === (int) $store->id) {
                throw ValidationException::withMessages(['to_store_id' => 'Choose a different store.']);
            }
            if ($to && ! in_array((int) $to->branch_id, \App\Support\BranchContext::allowed(), true)) {
                throw ValidationException::withMessages(['to_store_id' => 'You do not have access to the destination store.']);
            }
            if ($type === 'adjust' && empty(trim((string) ($data['notes'] ?? '')))) {
                throw ValidationException::withMessages(['notes' => 'Write the reason for the adjustment (damage, count difference…).']);
            }

            $prefix = ['issue' => 'ISS', 'return' => 'RTN', 'transfer' => 'TRF', 'adjust' => 'ADJ'][$type];
            $entry = StockEntry::create([
                'branch_id' => $store->branch_id,
                'number' => DocumentNumber::next($prefix, $store->branch_id),
                'type' => $type, 'store_id' => $store->id, 'to_store_id' => $to?->id, 'work_project_id' => $project?->id,
                'date' => $data['date'], 'notes' => $data['notes'] ?? null, 'status' => 'active', 'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $row) {
                $item = Item::findOrFail($row['item_id']);
                $qty = round((float) $row['qty'], 3);
                $cost = (float) $item->avg_cost;
                $note = "{$entry->number}";
                match ($type) {
                    'issue' => Stock::out($store, $item, $qty, 'issue', $data['date'], $project->id, $entry, $note),
                    'return' => Stock::in($store, $item, $qty, $cost, 'return', $data['date'], $project->id, $entry, $note),
                    'transfer' => [Stock::out($store, $item, $qty, 'transfer_out', $data['date'], null, $entry, $note),
                        Stock::in($to, $item, $qty, $cost, 'transfer_in', $data['date'], null, $entry, $note)],
                    'adjust' => $qty >= 0
                        ? Stock::in($store, $item, $qty, $cost, 'adjust', $data['date'], null, $entry, $note)
                        : Stock::out($store, $item, -$qty, 'adjust', $data['date'], null, $entry, $note),
                };
                $entry->items()->create(['item_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost, 'amount' => round(abs($qty) * $cost, 2)]);
            }

            return $entry;
        });
    }

    public static function cancelEntry(StockEntry $entry, string $reason): void
    {
        DB::transaction(function () use ($entry, $reason) {
            $e = StockEntry::lockForUpdate()->findOrFail($entry->id);
            if ($e->status === 'cancelled') {
                return;
            }
            $moves = StockMovement::withoutGlobalScopes()->where('source_type', $e->getMorphClass())->where('source_id', $e->id)
                ->where('type', '!=', 'reverse')->orderByDesc('qty')->get(); // put back (+) before taking out (−)
            foreach ($moves->sortBy(fn ($m) => (float) $m->qty < 0 ? 0 : 1) as $m) {
                Stock::reverse($m, now()->toDateString());
            }
            $e->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        });
    }

    public static function paySupplier(array $data, ?int $branchId = null): SupplierPayment
    {
        return DB::transaction(function () use ($data, $branchId) {
            $account = Account::withoutGlobalScopes()->findOrFail($data['account_id']);
            if (! in_array((int) $account->branch_id, \App\Support\BranchContext::allowed(), true)) {
                throw ValidationException::withMessages(['account_id' => 'You do not have access to this account.']);
            }
            $branchId ??= $account->branch_id;
            if ((int) $account->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages(['account_id' => 'Pay from an account of the same branch.']);
            }
            if ($account->balance() < (float) $data['amount']) {
                throw ValidationException::withMessages(['amount' => "Not enough balance in {$account->name} (".Money::format($account->balance()).').']);
            }
            $supplier = ! empty($data['supplier_id']) ? Supplier::findOrFail($data['supplier_id']) : null;
            $purchase = ! empty($data['purchase_id']) ? Purchase::lockForUpdate()->findOrFail($data['purchase_id']) : null;
            if ($purchase) {
                $left = round((float) $purchase->total - (float) $purchase->paid, 2);
                if ((float) $data['amount'] > $left + 0.004) {
                    throw ValidationException::withMessages(['amount' => 'More than the unpaid amount of this purchase ('.Money::format($left).').']);
                }
            }

            $payment = SupplierPayment::create([
                'branch_id' => $branchId,
                'number' => DocumentNumber::next('SP', $branchId),
                'supplier_id' => $supplier?->id ?? $purchase?->supplier_id,
                'purchase_id' => $purchase?->id,
                'account_id' => $account->id,
                'method' => $data['method'] ?? 'cash',
                'amount' => round((float) $data['amount'], 2),
                'reference' => $data['reference'] ?? null,
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'paid',
                'created_by' => Auth::id(),
            ]);
            // Material cost is already counted on the project through the purchase, so the
            // ledger entry is not linked to a project (no double counting).
            Ledger::post([
                'type' => 'expense', 'account_id' => $account->id, 'amount' => $payment->amount, 'date' => $data['date'],
                'category_id' => TransactionCategory::idFor('supplier_payment'),
                'description' => "Supplier payment {$payment->number}".($supplier ? " to {$supplier->name}" : '').($purchase ? " for {$purchase->number}" : ''),
                'reference' => $data['reference'] ?? null,
            ], $payment, $supplier);
            if ($purchase) {
                $purchase->increment('paid', $payment->amount);
            }

            return $payment;
        });
    }

    public static function cancelSupplierPayment(SupplierPayment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $p = SupplierPayment::lockForUpdate()->findOrFail($payment->id);
            if ($p->status === 'cancelled') {
                return;
            }
            $p->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            Ledger::cancelFor($p, "Supplier payment {$p->number} cancelled: {$reason}");
            if ($p->purchase_id) {
                Purchase::whereKey($p->purchase_id)->decrement('paid', $p->amount);
            }
        });
    }
}
