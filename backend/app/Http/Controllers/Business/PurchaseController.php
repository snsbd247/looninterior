<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Item;
use App\Models\Business\Purchase;
use App\Models\Business\PurchaseOrder;
use App\Models\Business\Store;
use App\Models\Business\Supplier;
use App\Services\DocumentNumber;
use App\Services\Documents;
use App\Services\Purchasing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    // ---------------- Purchase orders ----------------

    public function orders(Request $request)
    {
        $q = PurchaseOrder::query()->with(['supplier:id,name', 'store:id,name', 'branch:id,code']);
        foreach (['status', 'supplier_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%{$s}%")));
        }

        return response()->json($q->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function showOrder(PurchaseOrder $order)
    {
        return response()->json(['data' => $order->load(['items.item:id,name,unit,sku', 'supplier', 'store:id,name', 'creator:id,name', 'branch:id,name,code', 'purchases:id,purchase_order_id,number,date,total,status'])]);
    }

    public function storeOrder(Request $request, ?PurchaseOrder $order = null)
    {
        if ($order && $order->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft purchase orders can be edited.']);
        }
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'store_id' => 'required|integer|exists:stores,id',
            'date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:200',
            'items.*.item_id' => 'required|integer|exists:items,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.rate' => 'required|numeric|min:0',
        ]);
        $store = Store::findOrFail($data['store_id']);

        $po = DB::transaction(function () use ($data, $store, $order, $request) {
            $rows = collect($data['items'])->values()->map(fn ($r, $i) => [
                'item_id' => $r['item_id'], 'description' => $r['description'] ?? null,
                'qty' => round((float) $r['qty'], 3), 'rate' => round((float) $r['rate'], 2),
                'amount' => round((float) $r['qty'] * (float) $r['rate'], 2), 'sort_order' => $i,
            ]);
            $header = [
                'supplier_id' => $data['supplier_id'], 'store_id' => $store->id, 'date' => $data['date'],
                'expected_date' => $data['expected_date'] ?? null, 'notes' => $data['notes'] ?? null, 'total' => round($rows->sum('amount'), 2),
            ];
            if ($order) {
                $order->update($header);
                $order->items()->delete();
            } else {
                $order = PurchaseOrder::create($header + [
                    'branch_id' => $store->branch_id, 'number' => DocumentNumber::next('PO', $store->branch_id),
                    'status' => 'draft', 'created_by' => $request->user()->id,
                ]);
            }
            $order->items()->createMany($rows->all());

            return $order;
        });

        return response()->json(['message' => "Purchase order {$po->number} saved", 'data' => $po], $order ? 200 : 201);
    }

    public function orderStatus(Request $request, PurchaseOrder $order)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['sent', 'cancelled'])]]);
        $ok = $data['status'] === 'sent' ? $order->status === 'draft' : in_array($order->status, ['draft', 'sent'], true);
        if (! $ok) {
            throw ValidationException::withMessages(['status' => "A {$order->status} order cannot be marked {$data['status']}."]);
        }
        $order->update(['status' => $data['status']]);

        return response()->json(['message' => 'Purchase order '.$data['status'], 'data' => $order]);
    }

    public function orderPdf(Request $request, PurchaseOrder $order)
    {
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';
        $order->load(['items.item', 'supplier', 'store']);
        $doc = [
            'title' => __('doc.purchase_order', [], $lang),
            'number' => $order->number,
            'date' => Documents::d($order->date),
            'meta' => array_filter([($lang === 'bn' ? 'ডেলিভারি' : 'Deliver by') => $order->expected_date ? Documents::d($order->expected_date) : null, ($lang === 'bn' ? 'স্টোর' : 'Deliver to') => $order->store?->name]),
            'party_label' => $lang === 'bn' ? 'সরবরাহকারী' : 'Supplier',
            'party' => array_values(array_filter([$order->supplier?->name, $order->supplier?->address, $order->supplier?->phone])),
            'sections' => [['name' => null, 'items' => $order->items->map(fn ($i) => ['description' => $i->description ?: $i->item?->name, 'unit' => $i->item?->unit, 'qty' => $i->qty, 'rate' => $i->rate, 'amount' => $i->amount])->all()]],
            'subtotal' => $order->total, 'discount' => 0, 'tax_label' => '', 'tax' => 0, 'total' => $order->total,
            'notes' => $order->notes, 'hide_terms' => true,
            'watermark' => $order->status === 'cancelled' ? 'CANCELLED' : null,
        ];
        $pdf = Documents::pdf($doc, $lang);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$order->number.'.pdf"']);
    }

    public function emailOrder(Request $request, PurchaseOrder $order)
    {
        $data = $request->validate(['to' => 'required|email', 'lang' => 'nullable|in:en,bn', 'message' => 'nullable|string|max:3000']);
        // Re-use the PDF builder above through a sub-request-free call
        $lang = $data['lang'] ?? 'en';
        $pdfResponse = $this->orderPdf(new Request(['lang' => $lang]), $order);
        try {
            \Illuminate\Support\Facades\Mail::send('emails.document', [
                'site' => \App\Services\CompanyProfile::get($lang)['name'], 'title' => __('doc.purchase_order', [], $lang),
                'number' => $order->number, 'total' => \App\Services\Money::format($order->total, 'en', true), 'body' => $data['message'] ?? '',
            ], fn ($m) => $m->to($data['to'])->subject("Purchase Order {$order->number}")->attachData($pdfResponse->getContent(), "{$order->number}.pdf", ['mime' => 'application/pdf']));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'E-mail failed: '.$e->getMessage()], 422);
        }
        if ($order->status === 'draft') {
            $order->update(['status' => 'sent']);
        }
        \App\Support\ActivityLogger::record('emailed', $order, "E-mailed purchase order {$order->number} to {$data['to']}");

        return response()->json(['message' => "Sent to {$data['to']}"]);
    }

    // ---------------- Purchases (GRN / direct) ----------------

    public function index(Request $request)
    {
        $q = Purchase::query()->with(['supplier:id,name', 'store:id,name', 'project:id,code', 'branch:id,code']);
        foreach (['type', 'status', 'supplier_id', 'store_id', 'work_project_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('supplier_ref', 'like', "%{$s}%")->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%{$s}%")));
        }

        return response()->json($q->latest('date')->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function show(Purchase $purchase)
    {
        return response()->json(['data' => $purchase->load(['items.item:id,name,unit,sku', 'supplier', 'store:id,name', 'project:id,code,title', 'order:id,number', 'payments.account:id,name', 'creator:id,name', 'branch:id,name,code'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['grn', 'direct'])],
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'store_id' => 'nullable|required_if:type,grn|integer|exists:stores,id',
            'work_project_id' => 'nullable|required_if:type,direct|integer|exists:work_projects,id',
            'purchase_order_id' => 'nullable|integer|exists:purchase_orders,id',
            'supplier_ref' => 'nullable|string|max:100',
            'date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:2000',
            'pay_now_account_id' => 'nullable|integer|exists:accounts,id',
            'pay_method' => 'nullable|string|max:20',
            'items' => 'required|array|min:1|max:200',
            'items.*.item_id' => 'nullable|integer|exists:items,id',
            'items.*.purchase_order_item_id' => 'nullable|integer|exists:purchase_order_items,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.qty' => 'required|numeric|min:0.001|max:99999999',
            'items.*.rate' => 'required|numeric|min:0|max:9999999999',
        ]);
        if (! empty($data['pay_now_account_id']) && ! $request->user()->hasPermission('suppliers.pay')) {
            throw ValidationException::withMessages(['pay_now_account_id' => 'You do not have permission to pay suppliers.']);
        }
        $purchase = Purchasing::createPurchase($data);

        return response()->json(['message' => "{$purchase->number} saved", 'data' => $purchase], 201);
    }

    public function cancel(Request $request, Purchase $purchase)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        Purchasing::cancelPurchase($purchase, $data['reason']);

        return response()->json(['message' => 'Purchase cancelled']);
    }
}
