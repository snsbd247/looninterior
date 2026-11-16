<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Item;
use App\Models\Business\StockBalance;
use App\Models\Business\StockEntry;
use App\Models\Business\StockMovement;
use App\Models\Business\Store;
use App\Services\Purchasing;
use App\Support\BranchContext;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    // ---------------- Stores ----------------

    public function stores(Request $request)
    {
        return response()->json(['data' => Store::with('branch:id,code')->when(! $request->boolean('inactive'), fn ($q) => $q->where('is_active', true))->orderBy('branch_id')->orderBy('name')->get()]);
    }

    public function saveStore(Request $request, ?Store $store = null)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);
        if ($store) {
            unset($data['branch_id']);
            $store->update($data);
        } else {
            $data['branch_id'] = $data['branch_id'] ?? BranchContext::forWrite();
            $store = Store::create($data);
        }

        return response()->json(['message' => 'Store saved', 'data' => $store], $request->route('store') ? 200 : 201);
    }

    /** Item list for dropdowns (company-wide). */
    public function items(Request $request)
    {
        return response()->json(['data' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'avg_cost', 'item_category_id'])]);
    }

    // ---------------- Stock ----------------

    /** Current stock per item (and per store), with value and low-stock flag. */
    public function stock(Request $request)
    {
        $storeIds = Store::query()->when($request->filled('store_id'), fn ($q) => $q->where('id', $request->query('store_id')))->pluck('id');
        $balances = StockBalance::whereIn('store_id', $storeIds)->get()->groupBy('item_id');

        $items = Item::query()->with('category:id,name')
            ->when($request->filled('category_id'), fn ($q) => $q->where('item_category_id', $request->query('category_id')))
            ->when(trim((string) $request->query('q')) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('sku', 'like', '%'.$request->query('q').'%')))
            ->orderBy('name')->get()
            ->map(function (Item $i) use ($balances) {
                $rows = $balances->get($i->id, collect());
                $qty = round((float) $rows->sum('qty'), 3);

                return [
                    'id' => $i->id, 'sku' => $i->sku, 'name' => $i->name, 'unit' => $i->unit, 'category' => $i->category?->name,
                    'qty' => $qty, 'min_stock' => (float) $i->min_stock, 'avg_cost' => round((float) $i->avg_cost, 2),
                    'value' => round($qty * (float) $i->avg_cost, 2), 'low' => (float) $i->min_stock > 0 && $qty <= (float) $i->min_stock,
                    'stores' => $rows->filter(fn ($b) => (float) $b->qty != 0)->map(fn ($b) => ['store_id' => $b->store_id, 'qty' => (float) $b->qty])->values(),
                    'is_active' => $i->is_active,
                ];
            })
            ->when($request->boolean('low'), fn ($c) => $c->where('low', true))
            ->when($request->boolean('in_stock'), fn ($c) => $c->where('qty', '>', 0))
            ->values();

        if ($request->query('format') === 'csv') {
            return Csv::download('stock.csv', ['SKU', 'Item', 'Category', 'Unit', 'Qty', 'Min stock', 'Avg cost', 'Value', 'Low'],
                $items->map(fn ($r) => [$r['sku'], $r['name'], $r['category'], $r['unit'], $r['qty'], $r['min_stock'], $r['avg_cost'], $r['value'], $r['low']])->all());
        }

        return response()->json([
            'data' => $items,
            'stores' => Store::whereIn('id', $storeIds)->get(['id', 'name', 'branch_id']),
            'total_value' => round($items->sum('value'), 2),
            'low_count' => $items->where('low', true)->count(),
        ]);
    }

    /** Movement history of one item (in the user's branch scope). */
    public function itemLedger(Request $request, Item $item)
    {
        $moves = StockMovement::where('item_id', $item->id)
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->query('store_id')))
            ->with(['store:id,name', 'project:id,code'])->orderBy('date')->orderBy('id')->limit(2000)->get();
        $running = 0;
        $lines = $moves->map(function ($m) use (&$running) {
            $running = round($running + (float) $m->qty, 3);

            return array_merge($m->toArray(), ['balance' => $running]);
        });

        return response()->json(['data' => $item->load('category:id,name'), 'lines' => $lines->reverse()->values()]);
    }

    // ---------------- Stock entries (issue / return / transfer / adjust) ----------------

    public function entries(Request $request)
    {
        $q = StockEntry::query()->with(['store:id,name', 'toStore:id,name', 'project:id,code,title', 'creator:id,name'])->withCount('items');
        foreach (['type', 'store_id', 'work_project_id', 'status'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }

        return response()->json($q->latest('date')->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function showEntry(StockEntry $entry)
    {
        return response()->json(['data' => $entry->load(['items.item:id,name,unit,sku', 'store:id,name', 'toStore:id,name', 'project:id,code,title', 'creator:id,name'])]);
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(StockEntry::TYPES)],
            'store_id' => 'required|integer|exists:stores,id',
            'to_store_id' => 'nullable|required_if:type,transfer|integer|exists:stores,id',
            'work_project_id' => 'nullable|required_if:type,issue|required_if:type,return|integer|exists:work_projects,id',
            'date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1|max:200',
            'items.*.item_id' => 'required|integer|exists:items,id',
            'items.*.qty' => ['required', 'numeric', $request->input('type') === 'adjust' ? 'not_in:0' : 'min:0.001', 'max:99999999'],
        ]);
        $entry = Purchasing::createEntry($data);

        return response()->json(['message' => "{$entry->number} saved", 'data' => $entry], 201);
    }

    public function cancelEntry(Request $request, StockEntry $entry)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        Purchasing::cancelEntry($entry, $data['reason']);

        return response()->json(['message' => 'Cancelled']);
    }
}
