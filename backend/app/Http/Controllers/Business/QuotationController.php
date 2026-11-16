<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Customer;
use App\Models\Business\Lead;
use App\Models\Business\LeadActivity;
use App\Models\Business\Quotation;
use App\Services\DocumentNumber;
use App\Services\Documents;
use App\Services\DocumentTotals;
use App\Support\ActivityLogger;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $q = Quotation::query()->with(['customer:id,name,phone', 'branch:id,code']);
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        } elseif (! $request->boolean('all_versions')) {
            $q->where('status', '!=', 'revised'); // show the latest version only
        }
        if ($request->filled('customer_id')) {
            $q->where('customer_id', $request->query('customer_id'));
        }

        return response()->json($q->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['items', 'customer', 'lead:id,name,stage', 'creator:id,name', 'branch:id,name,code']);
        $versions = Quotation::where('base_number', $quotation->base_number)->orderBy('revision')
            ->get(['id', 'number', 'revision', 'status', 'total', 'date']);

        return response()->json([
            'data' => $quotation,
            'versions' => $versions,
            'share_url' => $this->shareUrl($quotation),
            'projects' => class_exists(\App\Models\Business\WorkProject::class)
                ? \App\Models\Business\WorkProject::where('quotation_id', $quotation->id)->get(['id', 'code', 'title', 'status'])
                : [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $customer = Customer::findOrFail($data['customer_id']); // branch-scoped
        $branchId = BranchContext::forWrite() ?? $customer->branch_id;
        if ((int) $customer->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['customer_id' => 'The customer belongs to another branch.']);
        }
        $totals = DocumentTotals::calculate($data['items'], $data['discount_type'] ?? 'amount', $data['discount_value'] ?? 0, $data['tax_rate_id'] ?? null);

        $quotation = DB::transaction(function () use ($data, $totals, $branchId, $request) {
            $number = DocumentNumber::next('QT', $branchId);
            $q = Quotation::create($this->header($data, $totals) + [
                'branch_id' => $branchId,
                'number' => $number,
                'base_number' => $number,
                'revision' => 0,
                'status' => 'draft',
                'customer_id' => $data['customer_id'],
                'lead_id' => $data['lead_id'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $q->items()->createMany($totals['items']);

            return $q;
        });

        return response()->json(['message' => "Quotation {$quotation->number} created", 'data' => $quotation], 201);
    }

    public function update(Request $request, Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Only draft quotations can be edited. Make a revision instead.']);
        }
        $data = $this->validated($request);
        $totals = DocumentTotals::calculate($data['items'], $data['discount_type'] ?? 'amount', $data['discount_value'] ?? 0, $data['tax_rate_id'] ?? null);

        DB::transaction(function () use ($quotation, $data, $totals) {
            $quotation->update($this->header($data, $totals) + ['lead_id' => $data['lead_id'] ?? $quotation->lead_id]);
            $quotation->items()->delete();
            $quotation->items()->createMany($totals['items']);
        });

        return response()->json(['message' => 'Quotation updated', 'data' => $quotation->fresh('items')]);
    }

    /** sent / approved / rejected / cancelled. Keeps the CRM lead stage in step. */
    public function status(Request $request, Quotation $quotation)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['sent', 'approved', 'rejected', 'cancelled', 'draft'])]]);
        $to = $data['status'];
        $allowed = [
            'draft' => ['sent', 'approved', 'cancelled'],
            'sent' => ['approved', 'rejected', 'cancelled', 'draft'],
            'rejected' => ['sent', 'cancelled'],
            'approved' => ['cancelled'],
        ][$quotation->status] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "A {$quotation->status} quotation cannot be marked {$to}."]);
        }

        DB::transaction(function () use ($quotation, $to, $request) {
            $quotation->status = $to;
            if ($to === 'sent' && ! $quotation->sent_at) {
                $quotation->sent_at = now();
            }
            if ($to === 'approved') {
                $quotation->approved_at = now();
            }
            $quotation->save();
            $this->syncLead($quotation, $to, $request);
        });

        return response()->json(['message' => 'Quotation '.$to, 'data' => $quotation]);
    }

    /** New version (QT-0001-R1) from a sent / rejected quotation; the old one becomes "revised". */
    public function revise(Request $request, Quotation $quotation)
    {
        if (! in_array($quotation->status, ['sent', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Only sent or rejected quotations can be revised. Drafts can be edited directly.']);
        }

        $new = DB::transaction(function () use ($quotation, $request) {
            $rev = (int) Quotation::where('base_number', $quotation->base_number)->max('revision') + 1;
            $copy = $quotation->replicate(['number', 'revision', 'parent_id', 'status', 'sent_at', 'approved_at', 'created_by']);
            $copy->fill([
                'number' => $quotation->base_number.'-R'.$rev,
                'revision' => $rev,
                'parent_id' => $quotation->id,
                'status' => 'draft',
                'date' => now()->toDateString(),
                'created_by' => $request->user()->id,
            ])->save();
            $copy->items()->createMany($quotation->items->map(fn ($i) => $i->only(['section', 'description', 'unit', 'qty', 'rate', 'amount', 'sort_order']))->all());
            $quotation->update(['status' => 'revised']);

            return $copy;
        });

        return response()->json(['message' => "Revision {$new->number} created", 'data' => $new], 201);
    }

    public function pdf(Request $request, Quotation $quotation)
    {
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';

        return $this->pdfResponse($quotation, $lang, $request->boolean('download'));
    }

    public function email(Request $request, Quotation $quotation)
    {
        $data = $request->validate([
            'to' => 'required|email',
            'lang' => 'nullable|in:en,bn',
            'message' => 'nullable|string|max:3000',
        ]);
        try {
            Documents::email($quotation, $data['to'], Documents::quotation($quotation, $data['lang'] ?? 'en'), $data['lang'] ?? 'en', $data['message'] ?? '');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'E-mail failed: '.$e->getMessage()], 422);
        }
        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'sent', 'sent_at' => now()]);
            $this->syncLead($quotation, 'sent', $request);
        }

        return response()->json(['message' => "Sent to {$data['to']}", 'data' => $quotation]);
    }

    /** Public, signed link (for WhatsApp): works without login until it expires. */
    public function publicPdf(Request $request, int $quotation)
    {
        $q = Quotation::withoutGlobalScopes()->findOrFail($quotation);

        return $this->pdfResponse($q, $request->query('lang') === 'bn' ? 'bn' : 'en', false);
    }

    private function pdfResponse(Quotation $q, string $lang, bool $download)
    {
        $pdf = Documents::pdf(Documents::quotation($q, $lang), $lang);
        $name = preg_replace('/[^A-Za-z0-9\-]+/', '-', $q->number).($lang === 'bn' ? '-bn' : '').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"',
        ]);
    }

    private function shareUrl(Quotation $q): array
    {
        $exp = now()->addDays(30);

        return [
            'en' => URL::temporarySignedRoute('public.quotation.pdf', $exp, ['quotation' => $q->id, 'lang' => 'en']),
            'bn' => URL::temporarySignedRoute('public.quotation.pdf', $exp, ['quotation' => $q->id, 'lang' => 'bn']),
            'expires' => $exp->toDateString(),
        ];
    }

    private function syncLead(Quotation $q, string $status, Request $request): void
    {
        if (! $q->lead_id) {
            return;
        }
        $lead = Lead::find($q->lead_id);
        if (! $lead || in_array($lead->stage, ['won', 'lost'], true)) {
            return;
        }
        $order = array_flip(Lead::STAGES);
        $target = $status === 'sent' ? 'quotation_sent' : ($status === 'approved' ? 'won' : null);
        if (! $target || $order[$lead->stage] >= $order[$target]) {
            return;
        }
        $from = $lead->stage;
        $lead->stage = $target;
        if ($target === 'won') {
            $lead->won_at = now();
            $lead->next_follow_up_at = null;
            $lead->customer_id ??= $q->customer_id;
        }
        $lead->save();
        LeadActivity::create([
            'lead_id' => $lead->id, 'user_id' => $request->user()->id, 'type' => 'stage',
            'note' => 'Stage: '.str_replace('_', ' ', $from).' → '.str_replace('_', ' ', $target)." (quotation {$q->number} {$status})",
        ]);
    }

    private function header(array $data, array $totals): array
    {
        return [
            'title' => $data['title'] ?? null,
            'date' => $data['date'],
            'valid_until' => $data['valid_until'] ?? null,
            'terms' => $data['terms'] ?? null,
            'notes' => $data['notes'] ?? null,
        ] + collect($totals)->except('items')->all();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'lead_id' => 'nullable|integer|exists:leads,id',
            'title' => 'nullable|string|max:255',
            'date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:date',
            'discount_type' => 'nullable|in:amount,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'nullable|integer|exists:tax_rates,id',
            'terms' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:2000',
        ] + DocumentTotals::itemRules());
    }
}
