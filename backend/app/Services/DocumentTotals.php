<?php

namespace App\Services;

use App\Models\Business\TaxRate;
use Illuminate\Validation\ValidationException;

/**
 * Server-side totals for quotations / invoices / purchase docs. The browser may
 * show a preview, but these numbers are the only ones saved.
 *   subtotal = Σ round(qty × rate, 2)
 *   discount = amount, or subtotal × % (never more than subtotal)
 *   tax      = (subtotal − discount) × rate %
 *   total    = subtotal − discount + tax
 */
class DocumentTotals
{
    /** Validation rules for an items[] array. */
    public static function itemRules(bool $sections = true): array
    {
        return array_filter([
            'items' => 'required|array|min:1|max:500',
            'items.*.section' => $sections ? 'nullable|string|max:255' : null,
            'items.*.description' => 'required|string|max:2000',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.qty' => 'required|numeric|min:0.001|max:99999999',
            'items.*.rate' => 'required|numeric|min:0|max:9999999999',
        ]);
    }

    /**
     * @return array{items: array, subtotal: float, discount: float, tax: float, total: float,
     *               tax_rate_id: ?int, tax_name: ?string, tax_rate: float, discount_type: string, discount_value: float}
     */
    public static function calculate(array $items, string $discountType = 'amount', float|string|null $discountValue = 0, ?int $taxRateId = null): array
    {
        $rows = [];
        $subtotal = 0.0;
        foreach (array_values($items) as $i => $item) {
            $qty = round((float) $item['qty'], 3);
            $rate = round((float) $item['rate'], 2);
            $amount = round($qty * $rate, 2);
            $subtotal += $amount;
            $rows[] = [
                'section' => isset($item['section']) && trim((string) $item['section']) !== '' ? trim($item['section']) : null,
                'description' => trim((string) $item['description']),
                'unit' => $item['unit'] ?? null,
                'qty' => $qty,
                'rate' => $rate,
                'amount' => $amount,
                'sort_order' => $i,
            ];
        }
        $subtotal = round($subtotal, 2);

        $discountType = $discountType === 'percent' ? 'percent' : 'amount';
        $discountValue = max(0, round((float) $discountValue, 2));
        if ($discountType === 'percent' && $discountValue > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Discount cannot be more than 100%.']);
        }
        $discount = $discountType === 'percent' ? round($subtotal * $discountValue / 100, 2) : $discountValue;
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_value' => 'Discount cannot be more than the subtotal.']);
        }

        $tax = null;
        if ($taxRateId) {
            $tax = TaxRate::whereKey($taxRateId)->where('is_active', true)->first();
            if (! $tax) {
                throw ValidationException::withMessages(['tax_rate_id' => 'Select an active tax rate.']);
            }
        }
        $rate = $tax ? (float) $tax->rate : 0.0;
        $taxAmount = round(($subtotal - $discount) * $rate / 100, 2);

        return [
            'items' => $rows,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount' => $discount,
            'tax_rate_id' => $tax?->id,
            'tax_name' => $tax?->name,
            'tax_rate' => $rate,
            'tax' => $taxAmount,
            'total' => round($subtotal - $discount + $taxAmount, 2),
        ];
    }

    /** "VAT (15%)" */
    public static function taxLabel(?string $name, float|string|null $rate): string
    {
        if (! $name) {
            return '';
        }
        $r = rtrim(rtrim(number_format((float) $rate, 3, '.', ''), '0'), '.');

        return "{$name} ({$r}%)";
    }

    /** Group saved item rows into PDF sections. */
    public static function sections($items): array
    {
        $out = [];
        foreach ($items as $it) {
            $key = $it->section ?? '';
            if (! isset($out[$key])) {
                $out[$key] = ['name' => $it->section, 'items' => []];
            }
            $out[$key]['items'][] = [
                'description' => $it->description, 'unit' => $it->unit,
                'qty' => $it->qty, 'rate' => $it->rate, 'amount' => $it->amount,
            ];
        }

        return array_values($out);
    }
}
