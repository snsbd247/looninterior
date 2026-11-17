<?php

namespace App\Support;

use App\Models\CalculatorOption;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * Website cost calculator. The browser shows the same formula live; the server always recomputes it
 * (for the lead note and the PDF), so a visitor can never send their own numbers.
 *
 *   By area:      rate = type Tk/sqft × style × quality;  work = rate × area
 *   Room by room: work = Σ (room Tk × count) × style × quality  (area is used only for the timeline)
 *   estimate = work + Σ add-ons
 *   range    = estimate − low% … estimate + high%
 *   timeline = base days + days per 1,000 sqft × area / 1,000 (rounded up to 5 days)
 */
class CostEstimate
{
    public const DEFAULTS = [
        'calc_min_area' => 200, 'calc_max_area' => 20000, 'calc_default_area' => 1200,
        'calc_range_low' => 10, 'calc_range_high' => 15,
        'calc_days_base' => 30, 'calc_days_per_1000' => 35,
        'calc_breakdown' => "Materials: 45\nWorkmanship: 25\nFurniture & Fittings: 15\nDesign & Supervision: 8\nElectrical & Lighting: 4\nOther: 3",
        'calc_note' => 'This is a ballpark estimate. The final quotation (BOQ) is itemised after a free site visit.',
    ];

    /** Calculator settings (website settings with the calc_ prefix) with defaults. */
    public static function settings(?array $s = null): array
    {
        $s ??= Setting::publicArray();
        $out = [];
        foreach (self::DEFAULTS as $k => $default) {
            $v = $s[$k] ?? null;
            $out[$k] = ($v === null || $v === '') ? $default : (is_numeric($default) ? (float) $v : (string) $v);
        }
        $out['breakdown'] = self::breakdown($out['calc_breakdown']);

        return $out;
    }

    /** "Materials: 45" lines → [[name, percent]] (percentages are scaled to 100). */
    public static function breakdown(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (preg_match('/^\s*(.+?)\s*[:=]\s*([\d.]+)\s*%?\s*$/', $line, $m) && (float) $m[2] > 0) {
                $rows[] = [trim($m[1]), (float) $m[2]];
            }
        }
        $sum = array_sum(array_column($rows, 1)) ?: 1;

        return array_map(fn ($r) => ['name' => $r[0], 'percent' => round($r[1] * 100 / $sum, 1)], $rows);
    }

    /** Active options grouped for the page. */
    public static function options(): array
    {
        $all = CalculatorOption::active()->ordered()->get(['id', 'group', 'name', 'description', 'icon', 'value', 'is_default', 'default_qty']);
        $out = array_fill_keys(CalculatorOption::GROUPS, []);
        foreach ($all as $o) {
            $out[$o->group][] = $o;
        }

        return $out;
    }

    /**
     * @param  array{type_id:int, style_id:int, quality_id:int, area:numeric, addon_ids?:int[]}  $in
     */
    public static function calculate(array $in): array
    {
        $s = self::settings();
        $opts = CalculatorOption::active()->get()->keyBy('id');
        $pick = function (string $group, $id) use ($opts) {
            $o = $opts->get((int) $id);
            if (! $o || $o->group !== $group) {
                throw ValidationException::withMessages(["{$group}_id" => 'Please choose a valid option.']);
            }

            return $o;
        };
        $type = $pick('type', $in['type_id'] ?? 0);
        $style = $pick('style', $in['style_id'] ?? 0);
        $quality = $pick('quality', $in['quality_id'] ?? 0);
        $area = (float) ($in['area'] ?? 0);
        if ($area < $s['calc_min_area'] || $area > $s['calc_max_area']) {
            throw ValidationException::withMessages(['area' => "Area must be between {$s['calc_min_area']} and {$s['calc_max_area']} sqft."]);
        }
        $addons = collect(array_unique(array_map('intval', $in['addon_ids'] ?? [])))->map(fn ($id) => $pick('addon', $id))->values();
        $factor = $style->value * $quality->value;
        $mode = ($in['mode'] ?? 'area') === 'rooms' ? 'rooms' : 'area';
        $rooms = [];

        if ($mode === 'rooms') {
            // Room by room: Σ (room price × count) × style × quality. The area is still used for the timeline.
            foreach ($in['rooms'] ?? [] as $r) {
                $qty = (int) ($r['qty'] ?? 0);
                if ($qty < 0 || $qty > 20) {
                    throw ValidationException::withMessages(['rooms' => 'Room count must be between 0 and 20.']);
                }
                if ($qty > 0) {
                    $room = $pick('room', $r['id'] ?? 0);
                    $unit = round($room->value * $factor, 2);
                    $rooms[] = ['name' => $room->name, 'qty' => $qty, 'unit' => $unit, 'amount' => round($unit * $qty, 2)];
                }
            }
            if (! $rooms) {
                throw ValidationException::withMessages(['rooms' => 'Add at least one room.']);
            }
            $work = round(array_sum(array_column($rooms, 'amount')), 2);
            $rate = round($work / $area, 2); // effective Tk per sqft, for information
        } else {
            $rate = round($type->value * $factor, 2);
            $work = round($rate * $area, 2);
        }
        $extras = round((float) $addons->sum('value'), 2);
        $estimate = round($work + $extras, 2);
        $days = $s['calc_days_base'] + $s['calc_days_per_1000'] * $area / 1000;

        return [
            'mode' => $mode, 'rooms' => $rooms,
            'type' => $type->name, 'style' => $style->name, 'quality' => $quality->name, 'area' => $area,
            'base_rate' => (float) $type->value, 'style_factor' => (float) $style->value, 'quality_factor' => (float) $quality->value,
            'rate' => $rate, 'work' => $work,
            'addons' => $addons->map(fn ($a) => ['name' => $a->name, 'amount' => (float) $a->value])->all(),
            'addons_total' => $extras,
            'estimate' => $estimate,
            'low' => round($estimate * (1 - $s['calc_range_low'] / 100) / 1000) * 1000,
            'high' => round($estimate * (1 + $s['calc_range_high'] / 100) / 1000) * 1000,
            'days' => (int) (ceil($days / 5) * 5),
            'breakdown' => array_map(fn ($b) => $b + ['amount' => round($estimate * $b['percent'] / 100)], $s['breakdown']),
        ];
    }

    /** One-line summary for leads and e-mails. */
    public static function summary(array $e): string
    {
        $tk = fn ($v) => 'Tk '.number_format($v);
        $rooms = ($e['mode'] ?? 'area') === 'rooms'
            ? 'Rooms: '.implode(', ', array_map(fn ($r) => "{$r['qty']} × {$r['name']}", $e['rooms'])).' → '.$tk($e['work'])
            : null;
        $lines = array_filter([
            "Cost calculator: {$e['type']}, ".number_format($e['area'])." sqft, {$e['style']} style, {$e['quality']} quality.",
            $rooms,
            ($rooms ? 'Effective rate ' : 'Rate ').$tk($e['rate']).'/sqft → '.$tk($e['work']).($e['addons'] ? ' + add-ons '.$tk($e['addons_total']).' ('.implode(', ', array_column($e['addons'], 'name')).')' : '').'.',
            'Estimate '.$tk($e['estimate']).' (range '.$tk($e['low']).' – '.$tk($e['high']).'), about '.$e['days'].' days.',
        ]);

        return implode("\n", $lines);
    }
}
