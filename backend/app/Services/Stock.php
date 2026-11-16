<?php

namespace App\Services;

use App\Models\Business\Item;
use App\Models\Business\Purchase;
use App\Models\Business\StockBalance;
use App\Models\Business\StockMovement;
use App\Models\Business\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The only way stock changes. Call inside the caller's DB::transaction.
 * Every change writes a stock_movements row and updates the stock_balances row
 * (locked FOR UPDATE), so stock can never go below zero, even with parallel saves.
 *
 * Valuation: weighted average cost per item (company-wide), recalculated on every receipt.
 */
class Stock
{
    /** Goods coming in at a purchase cost (GRN). Updates the item's average cost. */
    public static function receive(Store $store, Item $item, float $qty, float $unitCost, string $date, ?Model $source = null, string $type = 'in', ?string $note = null): StockMovement
    {
        self::positive($qty);
        $item = Item::lockForUpdate()->findOrFail($item->id);
        $totalQty = (float) StockBalance::where('item_id', $item->id)->sum('qty');
        $newQty = $totalQty + $qty;
        if ($newQty > 0) {
            $item->avg_cost = round((max(0, $totalQty) * (float) $item->avg_cost + $qty * $unitCost) / $newQty, 4);
            $item->saveQuietly();
        }

        return self::move($store, $item, $qty, $unitCost, $type, $date, null, $source, $note);
    }

    /** Goods leaving a store at the current average cost (issue to project, transfer out, negative adjustment). */
    public static function out(Store $store, Item $item, float $qty, string $type, string $date, ?int $projectId = null, ?Model $source = null, ?string $note = null): StockMovement
    {
        self::positive($qty);
        $cost = (float) Item::whereKey($item->id)->value('avg_cost');

        return self::move($store, $item, -$qty, $cost, $type, $date, $projectId, $source, $note);
    }

    /** Goods coming back without changing the average (return from project, transfer in, positive adjustment). */
    public static function in(Store $store, Item $item, float $qty, float $unitCost, string $type, string $date, ?int $projectId = null, ?Model $source = null, ?string $note = null): StockMovement
    {
        self::positive($qty);

        return self::move($store, $item, $qty, $unitCost, $type, $date, $projectId, $source, $note);
    }

    /** Undo a purchase receipt: removes the qty at its cost and corrects the average. */
    public static function unreceive(Store $store, Item $item, float $qty, float $unitCost, string $date, ?Model $source = null): StockMovement
    {
        self::positive($qty);
        $item = Item::lockForUpdate()->findOrFail($item->id);
        $totalQty = (float) StockBalance::where('item_id', $item->id)->sum('qty');
        $remaining = $totalQty - $qty;
        if ($remaining > 0) {
            $item->avg_cost = round(max(0, ($totalQty * (float) $item->avg_cost - $qty * $unitCost) / $remaining), 4);
            $item->saveQuietly();
        }

        return self::move($store, $item, -$qty, $unitCost, 'reverse', $date, null, $source, 'Purchase cancelled');
    }

    /** Exact opposite of a movement (cancelling an issue/return/transfer/adjustment). */
    public static function reverse(StockMovement $m, string $date): StockMovement
    {
        $store = Store::withoutGlobalScopes()->findOrFail($m->store_id);
        $item = Item::findOrFail($m->item_id);

        return self::move($store, $item, -(float) $m->qty, (float) $m->unit_cost, 'reverse', $date, $m->work_project_id, $m->source, 'Cancelled');
    }

    public static function available(int $storeId, int $itemId): float
    {
        return (float) (StockBalance::where('store_id', $storeId)->where('item_id', $itemId)->value('qty') ?? 0);
    }

    /** Material cost of a project: net value issued from stores + direct purchases. */
    public static function projectMaterialCost(int $projectId): float
    {
        $issued = -(float) StockMovement::withoutGlobalScopes()->where('work_project_id', $projectId)->sum('value');
        $direct = (float) Purchase::withoutGlobalScopes()->where('work_project_id', $projectId)->where('type', 'direct')->where('status', 'received')->sum('total');

        return round($issued + $direct, 2);
    }

    private static function move(Store $store, Item $item, float $qty, float $unitCost, string $type, string $date, ?int $projectId, ?Model $source, ?string $note): StockMovement
    {
        $balance = StockBalance::where('store_id', $store->id)->where('item_id', $item->id)->lockForUpdate()->first();
        if (! $balance) {
            StockBalance::insertOrIgnore(['store_id' => $store->id, 'item_id' => $item->id, 'qty' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $balance = StockBalance::where('store_id', $store->id)->where('item_id', $item->id)->lockForUpdate()->first();
        }
        $newQty = round((float) $balance->qty + $qty, 3);
        if ($newQty < -0.0005) {
            throw ValidationException::withMessages(['items' => "Not enough \"{$item->name}\" in {$store->name}. Available: ".rtrim(rtrim(number_format((float) $balance->qty, 3, '.', ''), '0'), '.')." {$item->unit}."]);
        }
        $balance->update(['qty' => $newQty]);

        return StockMovement::create([
            'branch_id' => $store->branch_id,
            'store_id' => $store->id,
            'item_id' => $item->id,
            'type' => $type,
            'qty' => round($qty, 3),
            'unit_cost' => round($unitCost, 4),
            'value' => round($qty * $unitCost, 2),
            'work_project_id' => $projectId,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'date' => $date,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }

    private static function positive(float $qty): void
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['items' => 'Quantity must be more than zero.']);
        }
    }
}
