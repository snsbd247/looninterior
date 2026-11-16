<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Customer;
use App\Models\Business\Invoice;
use App\Models\Business\Payment;
use App\Models\Business\PaymentSchedule;
use App\Models\Business\Quotation;
use App\Models\Business\WorkProject;
use App\Services\Billing;
use App\Services\DocumentNumber;
use App\Services\Documents;
use App\Services\DocumentTotals;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $q = Invoice::query()->with(['customer:id,name,phone', 'project:id,code', 'branch:id,code']);
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        if ($request->query('status') === 'overdue') {
            $q->whereIn('status', ['issued', 'partial'])->whereDate('due_date', '<', today());
        } elseif ($request->query('status') === 'unpaid') {
            $q->whereIn('status', ['issued', 'partial']);
        } elseif ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        foreach (['customer_id', 'work_project_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        $sum = (clone $q)->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(total),0) total, COALESCE(SUM(paid),0) paid, COALESCE(SUM(credited),0) credited')->first();

        return response()->json(array_merge(
            $q->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20))))->toArray(),
            ['summary' => ['total' => (float) $sum->total, 'paid' => (float) $sum->paid, 'due' => round((float) $sum->total - (float) $sum->paid - (float) $sum->credited, 2)]]
        ));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items', 'customer', 'project:id,code,title', 'creator:id,name', 'branch:id,name,code',
            'payments.account:id,name', 'payments.creator:id,name', 'creditNotes']);

        return response()->json(['data' => $invoice]);
    }

    /** Pre-filled form data from a project installment, a quotation, or a project. */
    public function prefill(Request $request)
    {
        if ($request->filled('schedule_id')) {
            $s = PaymentSchedule::findOrFail($request->query('schedule_id'));
            $p = WorkProject::findOrFail($s->work_project_id); // branch-scoped
            if ($s->invoice_id) {
                throw ValidationException::withMessages(['schedule_id' => 'This installment is already invoiced.']);
            }
            $label = $s->label.($s->percent !== null ? ' ('.rtrim(rtrim((string) $s->percent, '0'), '.').'%)' : '');

            return response()->json(['data' => [
                'customer_id' => $p->customer_id, 'work_project_id' => $p->id, 'quotation_id' => $p->quotation_id,
                'schedule_id' => $s->id, 'title' => "{$p->title}: {$s->label}", 'due_date' => $s->due_date?->toDateString(),
                'items' => [['section' => null, 'description' => "{$label} of contract value for {$p->title} ({$p->code})", 'unit' => 'job', 'qty' => 1, 'rate' => (float) $s->amount]],
            ]]);
        }
        if ($request->filled('quotation_id')) {
            $q = Quotation::with('items')->findOrFail($request->query('quotation_id'));
            $project = WorkProject::where('quotation_id', $q->id)->first();

            return response()->json(['data' => [
                'customer_id' => $q->customer_id, 'quotation_id' => $q->id, 'work_project_id' => $project?->id,
                'title' => $q->title, 'discount_type' => $q->discount_type, 'discount_value' => (float) $q->discount_value,
                'tax_rate_id' => $q->tax_rate_id, 'terms' => $q->terms,
                'items' => $q->items->map(fn ($i) => ['section' => $i->section, 'description' => $i->description, 'unit' => $i->unit, 'qty' => (float) $i->qty, 'rate' => (float) $i->rate])->all(),
            ]]);
        }
        if ($request->filled('project_id')) {
            $p = WorkProject::findOrFail($request->query('project_id'));

            return response()->json(['data' => ['customer_id' => $p->customer_id, 'work_project_id' => $p->id, 'quotation_id' => $p->quotation_id, 'title' => $p->title]]);
        }

        return response()->json(['data' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $customer = Customer::findOrFail($data['customer_id']);
        $project = ! empty($data['work_project_id']) ? WorkProject::findOrFail($data['work_project_id']) : null;
        $branchId = $project?->branch_id ?? BranchContext::forWrite() ?? $customer->branch_id;
        if ((int) $customer->branch_id !== (int) $branchId || ($project && (int) $project->customer_id !== (int) $customer->id)) {
            throw ValidationException::withMessages(['customer_id' => 'Customer, project and branch must match.']);
        }
        $schedule = ! empty($data['schedule_id']) ? PaymentSchedule::where('work_project_id', $project?->id)->findOrFail($data['schedule_id']) : null;
        $totals = DocumentTotals::calculate($data['items'], $data['discount_type'] ?? 'amount', $data['discount_value'] ?? 0, $data['tax_rate_id'] ?? null);

        $invoice = DB::transaction(function () use ($data, $totals, $branchId, $schedule, $request) {
            if ($schedule && PaymentSchedule::lockForUpdate()->find($schedule->id)->invoice_id) {
                throw ValidationException::withMessages(['schedule_id' => 'This installment is already invoiced.']);
            }
            $inv = Invoice::create($this->header($data, $totals) + [
                'branch_id' => $branchId,
                'number' => DocumentNumber::next('INV', $branchId),
                'customer_id' => $data['customer_id'],
                'work_project_id' => $data['work_project_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $inv->items()->createMany($totals['items']);
            $schedule?->update(['invoice_id' => $inv->id]);

            return $inv;
        });

        return response()->json(['message' => "Invoice {$invoice->number} created", 'data' => $invoice], 201);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft invoices can be edited. Use a credit note or cancel instead.']);
        }
        $data = $this->validated($request);
        $totals = DocumentTotals::calculate($data['items'], $data['discount_type'] ?? 'amount', $data['discount_value'] ?? 0, $data['tax_rate_id'] ?? null);
        DB::transaction(function () use ($invoice, $data, $totals) {
            $invoice->update($this->header($data, $totals));
            $invoice->items()->delete();
            $invoice->items()->createMany($totals['items']);
        });

        return response()->json(['message' => 'Invoice updated', 'data' => $invoice->fresh('items')]);
    }

    public function issue(Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only drafts can be issued.']);
        }
        if ((float) $invoice->total <= 0) {
            throw ValidationException::withMessages(['status' => 'Invoice total must be more than zero.']);
        }
        $invoice->update(['status' => 'issued', 'issued_at' => now()]);

        return response()->json(['message' => "Invoice {$invoice->number} issued", 'data' => $invoice]);
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        Billing::cancelInvoice($invoice, $data['reason']);

        return response()->json(['message' => 'Invoice cancelled', 'data' => $invoice->fresh()]);
    }

    public function receive(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|integer|exists:accounts,id',
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => 'nullable|string|max:100',
            'date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);
        Account::findOrFail($data['account_id']); // branch-scoped visibility
        $payment = Billing::receive($invoice, $data);

        return response()->json(['message' => "Payment {$payment->number} received", 'data' => $payment, 'invoice' => $invoice->fresh()], 201);
    }

    public function creditNote(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'date' => 'required|date',
        ]);
        $cn = Billing::creditNote($invoice, $data['amount'], $data['reason'], $data['date']);

        return response()->json(['message' => "Credit note {$cn->number} added", 'data' => $cn, 'invoice' => $invoice->fresh()], 201);
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';

        return $this->pdfResponse(Documents::invoice($invoice, $lang), $lang, $request->boolean('download'));
    }

    public function email(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['to' => 'required|email', 'lang' => 'nullable|in:en,bn', 'message' => 'nullable|string|max:3000']);
        if ($invoice->status === 'draft') {
            throw ValidationException::withMessages(['to' => 'Issue the invoice before sending it.']);
        }
        try {
            $lang = $data['lang'] ?? 'en';
            Documents::email($invoice, $data['to'], Documents::invoice($invoice, $lang), $lang, $data['message'] ?? '');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'E-mail failed: '.$e->getMessage()], 422);
        }

        return response()->json(['message' => "Sent to {$data['to']}"]);
    }

    /** Receivables: unpaid invoices grouped by age (current / 1-30 / 31-60 / 61-90 / 90+ days overdue). */
    public function receivables(Request $request)
    {
        $rows = Invoice::query()->with(['customer:id,name,phone', 'branch:id,code'])
            ->whereIn('status', ['issued', 'partial'])->orderBy('due_date')->get()
            ->filter(fn ($i) => (float) $i->due > 0)
            ->map(function ($i) {
                $days = $i->due_date && $i->due_date->lt(today()) ? (int) $i->due_date->diffInDays(today()) : 0;
                $bucket = $days === 0 ? 'current' : ($days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : ($days <= 90 ? 'd90' : 'd90p')));

                return array_merge($i->only(['id', 'number', 'date', 'due_date', 'total', 'paid', 'credited', 'status', 'customer_id']), [
                    'due' => (float) $i->due, 'days_overdue' => $days, 'bucket' => $bucket,
                    'customer' => $i->customer?->only(['id', 'name', 'phone']), 'branch' => $i->branch?->code,
                ]);
            })->values();

        $buckets = ['current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90p' => 0];
        foreach ($rows as $r) {
            $buckets[$r['bucket']] += $r['due'];
        }
        $byCustomer = $rows->groupBy('customer_id')->map(fn ($g) => [
            'customer' => $g->first()['customer'], 'invoices' => $g->count(), 'due' => round($g->sum('due'), 2),
            'oldest_days' => $g->max('days_overdue'),
        ])->sortByDesc('due')->values();

        if ($request->query('format') === 'csv') {
            return \App\Support\Csv::download('receivables.csv',
                ['Invoice', 'Customer', 'Phone', 'Date', 'Due date', 'Total', 'Received', 'Due', 'Days overdue', 'Branch'],
                $rows->map(fn ($r) => [$r['number'], $r['customer']['name'] ?? '', $r['customer']['phone'] ?? '', $r['date'], $r['due_date'], $r['total'], $r['paid'], $r['due'], $r['days_overdue'], $r['branch']])->all());
        }

        return response()->json([
            'data' => $rows, 'by_customer' => $byCustomer,
            'buckets' => array_map(fn ($v) => round($v, 2), $buckets), 'total' => round($rows->sum('due'), 2),
        ]);
    }

    private function pdfResponse(array $doc, string $lang, bool $download)
    {
        $pdf = Documents::pdf($doc, $lang);
        $name = preg_replace('/[^A-Za-z0-9\-]+/', '-', $doc['number']).($lang === 'bn' ? '-bn' : '').'.pdf';

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"']);
    }

    private function header(array $data, array $totals): array
    {
        return [
            'title' => $data['title'] ?? null,
            'date' => $data['date'],
            'due_date' => $data['due_date'] ?? null,
            'terms' => $data['terms'] ?? null,
            'notes' => $data['notes'] ?? null,
        ] + collect($totals)->except('items')->all();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'work_project_id' => 'nullable|integer|exists:work_projects,id',
            'quotation_id' => 'nullable|integer|exists:quotations,id',
            'schedule_id' => 'nullable|integer|exists:payment_schedules,id',
            'title' => 'nullable|string|max:255',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'discount_type' => 'nullable|in:amount,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'nullable|integer|exists:tax_rates,id',
            'terms' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:2000',
        ] + DocumentTotals::itemRules());
    }
}
