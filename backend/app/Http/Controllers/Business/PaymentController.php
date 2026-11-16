<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Payment;
use App\Services\Billing;
use App\Services\Documents;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $q = Payment::query()->with(['customer:id,name,phone', 'invoice:id,number', 'account:id,name', 'branch:id,code']);
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('reference', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        foreach (['status', 'method', 'account_id', 'customer_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('from')) {
            $q->whereDate('date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('date', '<=', $request->query('to'));
        }
        $total = (float) (clone $q)->where('status', 'received')->sum('amount');

        return response()->json(array_merge(
            $q->latest('date')->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20))))->toArray(),
            ['summary' => ['received' => round($total, 2)]]
        ));
    }

    public function show(Payment $payment)
    {
        return response()->json(['data' => $payment->load(['customer', 'invoice', 'account:id,name,type', 'creator:id,name', 'branch:id,name,code'])]);
    }

    public function cancel(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        Billing::cancelPayment($payment, $data['reason']);

        return response()->json(['message' => "Payment {$payment->number} cancelled", 'data' => $payment->fresh()]);
    }

    public function pdf(Request $request, Payment $payment)
    {
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';
        $doc = Documents::receipt($payment, $lang);
        $pdf = Documents::pdf($doc, $lang);
        $name = preg_replace('/[^A-Za-z0-9\-]+/', '-', $payment->number).($lang === 'bn' ? '-bn' : '').'.pdf';

        return response($pdf, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$name.'"']);
    }

    public function email(Request $request, Payment $payment)
    {
        $data = $request->validate(['to' => 'required|email', 'lang' => 'nullable|in:en,bn', 'message' => 'nullable|string|max:3000']);
        try {
            $lang = $data['lang'] ?? 'en';
            Documents::email($payment, $data['to'], Documents::receipt($payment, $lang), $lang, $data['message'] ?? '');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'E-mail failed: '.$e->getMessage()], 422);
        }

        return response()->json(['message' => "Sent to {$data['to']}"]);
    }
}
