<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Customer;
use App\Models\Business\Lead;
use App\Models\Business\LeadActivity;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $q = Lead::query()->with(['assignee:id,name', 'branch:id,code']);

        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        foreach (['stage', 'source'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('assigned_to')) {
            $request->query('assigned_to') === 'me'
                ? $q->where('assigned_to', $request->user()->id)
                : $q->where('assigned_to', $request->query('assigned_to'));
        }
        if ($request->query('follow') === 'due') {
            $q->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay());
        }

        if ($request->query('view') === 'board') {
            $leads = $q->whereNotIn('stage', $request->boolean('closed') ? [] : ['lost'])
                ->orderByRaw('next_follow_up_at IS NULL, next_follow_up_at')->latest('id')->limit(500)->get();

            return response()->json([
                'stages' => Lead::STAGES,
                'data' => collect(Lead::STAGES)->mapWithKeys(fn ($s) => [$s => $leads->where('stage', $s)->values()]),
            ]);
        }

        return response()->json($q->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function show(Lead $lead)
    {
        return response()->json(['data' => $lead->load(['assignee:id,name', 'customer', 'branch:id,name,code', 'activities.user:id,name', 'inquiry:id,type,created_at'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['stage'] ??= 'new';
        $lead = DB::transaction(function () use ($data, $request) {
            $lead = Lead::create($data);
            LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $request->user()->id, 'type' => 'note', 'note' => 'Lead created ('.str_replace('_', ' ', $lead->source).').']);

            return $lead;
        });

        return response()->json(['message' => 'Lead created', 'data' => $lead], 201);
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $this->validated($request, true);
        unset($data['stage'], $data['branch_id']); // stage via /stage, branch fixed
        $lead->update($data);

        return response()->json(['message' => 'Lead updated', 'data' => $lead->fresh(['assignee:id,name', 'customer'])]);
    }

    /** Move the lead in the pipeline. "won" creates/links the customer. */
    public function stage(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(Lead::STAGES)],
            'lost_reason' => 'nullable|string|max:255|required_if:stage,lost',
            'note' => 'nullable|string|max:2000',
        ]);
        if ($data['stage'] === $lead->stage) {
            return response()->json(['message' => 'No change', 'data' => $lead]);
        }

        DB::transaction(function () use ($lead, $data, $request) {
            $from = $lead->stage;
            $lead->stage = $data['stage'];
            $lead->lost_reason = $data['stage'] === 'lost' ? $data['lost_reason'] : null;
            if ($data['stage'] === 'won') {
                $lead->won_at = now();
                $lead->next_follow_up_at = null;
                if (! $lead->customer_id) {
                    $lead->customer_id = $this->customerFor($lead, $request)->id;
                }
            }
            if ($data['stage'] === 'lost') {
                $lead->next_follow_up_at = null;
            }
            $lead->save();

            LeadActivity::create([
                'lead_id' => $lead->id, 'user_id' => $request->user()->id, 'type' => 'stage',
                'note' => 'Stage: '.str_replace('_', ' ', $from).' → '.str_replace('_', ' ', $data['stage'])
                    .($data['stage'] === 'lost' ? ' (reason: '.$data['lost_reason'].')' : '')
                    .(! empty($data['note']) ? "\n".$data['note'] : ''),
            ]);
        });

        return response()->json(['message' => 'Stage updated', 'data' => $lead->fresh(['assignee:id,name', 'customer'])]);
    }

    /** Log a call / meeting / visit / note, optionally with a follow-up reminder. */
    public function addActivity(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['call', 'meeting', 'visit', 'note', 'follow_up'])],
            'note' => 'nullable|string|max:5000|required_without:follow_up_at',
            'follow_up_at' => 'nullable|date',
        ]);

        $activity = DB::transaction(function () use ($lead, $data, $request) {
            $activity = LeadActivity::create($data + ['lead_id' => $lead->id, 'user_id' => $request->user()->id]);
            if (in_array($lead->stage, ['new'], true) && in_array($data['type'], ['call', 'meeting', 'visit'], true)) {
                $lead->update(['stage' => 'contacted']);
            }
            $this->refreshFollowUp($lead);

            return $activity;
        });

        return response()->json(['message' => 'Saved', 'data' => $activity->load('user:id,name')], 201);
    }

    public function completeActivity(Request $request, LeadActivity $activity)
    {
        $lead = Lead::findOrFail($activity->lead_id); // branch-scoped
        $activity->update(['done_at' => $activity->done_at ? null : now()]);
        $this->refreshFollowUp($lead);

        return response()->json(['message' => $activity->done_at ? 'Follow-up done' : 'Follow-up reopened', 'data' => $activity]);
    }

    /** Create or link the customer for this lead (same phone in the branch = same customer). */
    public function convert(Request $request, Lead $lead)
    {
        $customer = DB::transaction(function () use ($lead, $request) {
            $customer = $lead->customer ?? $this->customerFor($lead, $request);
            $lead->update(['customer_id' => $customer->id]);

            return $customer;
        });

        return response()->json(['message' => 'Customer ready', 'data' => $customer], 201);
    }

    /** Open follow-ups: overdue + today (+ upcoming with ?upcoming=1). Only "mine" unless ?all=1 and allowed. */
    public function followUps(Request $request)
    {
        $q = LeadActivity::query()
            ->whereNull('done_at')->whereNotNull('follow_up_at')
            ->whereHas('lead', fn ($l) => $l->whereNotIn('stage', ['won', 'lost']))
            ->with(['lead:id,name,phone,stage,assigned_to,branch_id', 'user:id,name'])
            ->orderBy('follow_up_at');

        if (! $request->boolean('upcoming')) {
            $q->where('follow_up_at', '<=', now()->endOfDay());
        }
        if (! $request->boolean('all')) {
            $me = $request->user()->id;
            $q->where(fn ($w) => $w->where('user_id', $me)->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $me)));
        }

        return response()->json(['data' => $q->limit(200)->get()]);
    }

    private function customerFor(Lead $lead, Request $request): Customer
    {
        $existing = $lead->phone ? Customer::where('branch_id', $lead->branch_id)->where('phone', $lead->phone)->first() : null;
        if ($existing) {
            return $existing;
        }

        return Customer::create([
            'branch_id' => $lead->branch_id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'address' => $lead->address,
            'created_by' => $request->user()->id,
        ]);
    }

    private function refreshFollowUp(Lead $lead): void
    {
        $next = LeadActivity::where('lead_id', $lead->id)->whereNull('done_at')->whereNotNull('follow_up_at')->min('follow_up_at');
        $lead->forceFill(['next_follow_up_at' => $next])->saveQuietly();
    }

    private function validated(Request $request, bool $update = false): array
    {
        return $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => ($update ? 'sometimes|' : '').'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'source' => ['nullable', Rule::in(Lead::SOURCES)],
            'stage' => ['nullable', Rule::in(Lead::STAGES)],
            'service' => 'nullable|string|max:255',
            'property_type' => 'nullable|string|max:255',
            'area_size' => 'nullable|string|max:255',
            'budget' => 'nullable|string|max:255',
            'expected_value' => 'nullable|numeric|min:0|max:999999999999',
            'requirements' => 'nullable|string|max:5000',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);
    }
}
