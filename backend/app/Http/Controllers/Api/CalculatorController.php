<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\Documents;
use App\Services\LeadIntake;
use App\Support\CostEstimate;
use App\Support\Notifier;
use App\Support\SpamGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Website cost calculator (/cost-calculator).
 * - GET  /api/calculator         options + settings (the page computes the estimate live with the same formula)
 * - POST /api/calculator/quote   name + phone + selections → inquiry + CRM lead (server recomputes everything)
 * - GET  /api/public/estimates/{inquiry}/pdf  signed link to the estimate PDF
 */
class CalculatorController extends Controller
{
    public function show()
    {
        $s = CostEstimate::settings();

        return response()->json([
            'options' => CostEstimate::options(),
            'settings' => [
                'min_area' => $s['calc_min_area'], 'max_area' => $s['calc_max_area'], 'default_area' => $s['calc_default_area'],
                'range_low' => $s['calc_range_low'], 'range_high' => $s['calc_range_high'],
                'days_base' => $s['calc_days_base'], 'days_per_1000' => $s['calc_days_per_1000'],
                'breakdown' => $s['breakdown'], 'note' => $s['calc_note'],
            ],
        ]);
    }

    public function quote(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'location' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
            'type_id' => 'required|integer',
            'style_id' => 'required|integer',
            'quality_id' => 'required|integer',
            'area' => 'required|numeric',
            'addon_ids' => 'nullable|array|max:20',
            'addon_ids.*' => 'integer',
        ]);
        $e = CostEstimate::calculate($data);

        if (SpamGuard::isBot($request)) {
            return response()->json(['message' => 'Thank you! We will contact you shortly.', 'estimate' => $e], 201);
        }

        $tk = fn ($v) => 'Tk '.number_format($v);
        $inquiry = Inquiry::create([
            'type' => 'calculator',
            'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null, 'location' => $data['location'] ?? null,
            'service' => 'Interior design (cost calculator)',
            'property_type' => $e['type'],
            'area_size' => number_format($e['area']).' sqft',
            'budget' => $tk($e['low']).' – '.$tk($e['high']),
            'message' => trim(CostEstimate::summary($e)."\n".($data['message'] ?? '')),
            'details' => $e,
            'status' => 'new',
            'ip' => $request->ip(),
        ]);
        $lead = LeadIntake::fromInquiry($inquiry);
        if ($lead && ! $lead->expected_value) {
            $lead->forceFill(['expected_value' => $e['estimate']])->saveQuietly();
        }
        Notifier::inquiry($inquiry);

        return response()->json([
            'message' => 'Thank you! Your estimate is ready and our designer will call you soon.',
            'estimate' => $e,
            'pdf_url' => URL::temporarySignedRoute('public.estimate.pdf', now()->addDays(30), ['inquiry' => $inquiry->id]),
        ], 201);
    }

    public function pdf(Inquiry $inquiry)
    {
        abort_unless($inquiry->type === 'calculator' && $inquiry->details, 404);
        $e = $inquiry->details;
        $sections = [[
            'name' => 'Interior work',
            'items' => [[
                'description' => "{$e['type']} interior – {$e['style']} style, {$e['quality']} materials",
                'unit' => 'sqft', 'qty' => $e['area'], 'rate' => $e['rate'], 'amount' => $e['work'],
            ]],
        ]];
        if (! empty($e['addons'])) {
            $sections[] = ['name' => 'Additional services', 'items' => array_map(fn ($a) => [
                'description' => $a['name'], 'unit' => 'job', 'qty' => 1, 'rate' => $a['amount'], 'amount' => $a['amount'],
            ], $e['addons'])];
        }
        $tk = fn ($v) => 'Tk '.number_format($v);
        $breakdown = implode(', ', array_map(fn ($b) => "{$b['name']} {$b['percent']}%", $e['breakdown'] ?? []));
        $doc = [
            'title' => 'Cost Estimate',
            'number' => 'EST-'.str_pad((string) $inquiry->id, 5, '0', STR_PAD_LEFT),
            'date' => $inquiry->created_at->format('d/m/Y'),
            'meta' => ['Budget range' => $tk($e['low']).' – '.$tk($e['high']), 'Estimated time' => "about {$e['days']} days"],
            'party_label' => 'Prepared for',
            'party' => array_values(array_filter([$inquiry->name, $inquiry->phone, $inquiry->email, $inquiry->location])),
            'sections' => $sections,
            'subtotal' => $e['estimate'], 'discount' => 0, 'tax' => 0, 'tax_label' => '', 'total' => $e['estimate'],
            'notes' => CostEstimate::settings()['calc_note'].($breakdown ? "\nTypical cost split: {$breakdown}." : ''),
            'hide_terms' => true,
        ];

        return response(Documents::pdf($doc, 'en'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$doc['number'].'.pdf"',
        ]);
    }
}
