<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Campaign;
use App\Models\Business\CampaignRecipient;
use App\Models\Business\Customer;
use App\Models\Business\Lead;
use App\Models\Setting;
use App\Services\Sms;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Marketing campaigns: the same SMS or e-mail to a filtered list of leads/customers of one branch.
 *
 * Flow: draft → start (recipient list is frozen) → the browser calls batch() until nothing is pending → sent.
 * No queue worker is needed (cPanel). People who opted out, and duplicates, are skipped.
 * {name} and {company} in the message are filled in per person.
 */
class MarketingController extends Controller
{
    private const BATCH = ['sms' => 100, 'email' => 15];

    public function index()
    {
        return response()->json(['data' => Campaign::with(['branch:id,code', 'creator:id,name'])->latest('id')->limit(200)->get()]);
    }

    public function show(Request $request, Campaign $campaign)
    {
        $q = CampaignRecipient::where('campaign_id', $campaign->id)
            ->when($request->filled('status'), fn ($x) => $x->where('status', $request->query('status')))->orderBy('id');

        return response()->json([
            'data' => $campaign->load(['branch:id,name,code', 'creator:id,name']),
            'recipients' => $q->paginate(50),
            'counts' => CampaignRecipient::where('campaign_id', $campaign->id)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'sms_parts' => $campaign->channel === 'sms' ? Sms::parts($campaign->message) : null,
        ]);
    }

    /** Live count and sample for the audience filters (before saving). */
    public function preview(Request $request)
    {
        $data = $this->validated($request, true);
        $people = $this->resolve($data['channel'], $data['audience'] ?? [], null);

        return response()->json([
            'count' => count($people),
            'sample' => array_slice($people, 0, 8),
            'sms_parts' => $data['channel'] === 'sms' && ! empty($data['message']) ? Sms::parts($data['message']) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $c = Campaign::create($data);

        return response()->json(['message' => 'Campaign saved as draft', 'data' => $c], 201);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->draftOnly($campaign);
        $data = $this->validated($request);
        unset($data['branch_id']);
        $campaign->update($data);

        return response()->json(['message' => 'Campaign saved', 'data' => $campaign]);
    }

    public function destroy(Campaign $campaign)
    {
        $this->draftOnly($campaign);
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted']);
    }

    /** Freeze the recipient list and mark the campaign as sending. */
    public function start(Campaign $campaign)
    {
        $this->draftOnly($campaign);
        if ($campaign->channel === 'sms' && Sms::provider() === 'custom' && trim((string) (Setting::businessArray()['biz_sms_url'] ?? '')) === '') {
            Log::info("Campaign {$campaign->id}: no SMS gateway set, messages go to storage/logs/sms.log");
        }
        DB::transaction(function () use ($campaign) {
            $c = Campaign::lockForUpdate()->findOrFail($campaign->id);
            $this->draftOnly($c);
            $people = $this->resolve($c->channel, $c->audience ?? [], $c->branch_id);
            if (! $people) {
                throw ValidationException::withMessages(['audience' => 'Nobody matches these filters (or they have no '.($c->channel === 'sms' ? 'mobile number' : 'e-mail').').']);
            }
            $now = now();
            foreach (array_chunk($people, 500) as $chunk) {
                CampaignRecipient::insert(array_map(fn ($p) => [
                    'campaign_id' => $c->id, 'recipient_type' => $p['type'], 'recipient_id' => $p['id'], 'name' => $p['name'],
                    'to' => $p['to'], 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
            $c->update(['status' => 'sending', 'total' => count($people), 'sent' => 0, 'failed' => 0]);
        });

        return response()->json(['message' => 'Sending started', 'data' => $campaign->fresh()]);
    }

    /** Send the next batch. The browser keeps calling this until `done` is true. */
    public function batch(Campaign $campaign)
    {
        if ($campaign->status !== 'sending') {
            return response()->json(['done' => true, 'data' => $campaign]);
        }
        @set_time_limit(120);
        // Claim a batch inside a lock so two open tabs never send the same message twice.
        $rows = DB::transaction(function () use ($campaign) {
            $rows = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'pending')
                ->orderBy('id')->limit(self::BATCH[$campaign->channel])->lockForUpdate()->get();
            CampaignRecipient::whereIn('id', $rows->pluck('id'))->update(['status' => 'sending']);

            return $rows;
        });

        $company = Setting::businessArray()['biz_company_name'] ?? Notifier::siteName();
        $fill = fn (string $text, ?string $name) => strtr($text, ['{name}' => $name ?: 'Sir/Madam', '{company}' => $company]);

        if ($campaign->channel === 'sms') {
            // Same text for everyone unless {name} is used; group by final text so bulk sending still works.
            foreach ($rows->groupBy(fn ($r) => $fill($campaign->message, $r->name)) as $text => $group) {
                $result = Sms::sendMany($group->pluck('to')->all(), $text);
                foreach ($group as $r) {
                    [$ok, $detail] = $result[Sms::normalize($r->to)] ?? [false, 'No answer from the gateway'];
                    $r->update(['status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : $detail, 'sent_at' => $ok ? now() : null]);
                }
            }
        } else {
            $site = Notifier::siteName();
            foreach ($rows as $r) {
                try {
                    $unsubscribe = URL::temporarySignedRoute('public.unsubscribe', now()->addYear(), ['type' => $r->recipient_type, 'id' => $r->recipient_id]);
                    Mail::send('emails.campaign', ['site' => $site, 'body' => $fill($campaign->message, $r->name), 'unsubscribe' => $unsubscribe],
                        fn ($m) => $m->to($r->to, $r->name)->subject($fill((string) $campaign->subject, $r->name)));
                    $r->update(['status' => 'sent', 'error' => null, 'sent_at' => now()]);
                } catch (\Throwable $e) {
                    Log::error("Campaign {$campaign->id} e-mail to {$r->to} failed: ".$e->getMessage());
                    $r->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 190)]);
                }
            }
        }

        $counts = CampaignRecipient::where('campaign_id', $campaign->id)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $left = (int) ($counts['pending'] ?? 0) + (int) ($counts['sending'] ?? 0);
        $campaign->update(array_filter([
            'sent' => (int) ($counts['sent'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0),
            'status' => $left === 0 ? 'sent' : null, 'sent_at' => $left === 0 ? now() : null,
        ], fn ($v) => $v !== null));

        return response()->json(['done' => $left === 0, 'data' => $campaign->fresh(), 'counts' => $counts]);
    }

    /** Put failed recipients back in the queue (e.g. after topping up the SMS balance). */
    public function retry(Campaign $campaign)
    {
        if (! in_array($campaign->status, ['sent', 'sending', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Nothing to retry.']);
        }
        $n = CampaignRecipient::where('campaign_id', $campaign->id)->whereIn('status', ['failed', 'sending'])->update(['status' => 'pending', 'error' => null]);
        if ($n) {
            $campaign->update(['status' => 'sending']);
        }

        return response()->json(['message' => $n ? "{$n} will be sent again" : 'Nothing failed', 'retry' => $n]);
    }

    /** Stop: messages not sent yet stay unsent. */
    public function stop(Campaign $campaign)
    {
        if ($campaign->status !== 'sending') {
            throw ValidationException::withMessages(['status' => 'The campaign is not sending.']);
        }
        CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'sending')->update(['status' => 'pending']);
        $campaign->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Campaign stopped']);
    }

    /** Public link in campaign e-mails (signed). */
    public function unsubscribe(Request $request, string $type, int $id)
    {
        $model = $type === 'lead' ? Lead::class : ($type === 'customer' ? Customer::class : null);
        abort_unless($model, 404);
        $person = $model::withoutGlobalScopes()->find($id);
        if ($person) {
            $person->forceFill(['marketing_opt_out' => true])->saveQuietly();
        }
        $site = e(Notifier::siteName());

        return response("<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width'><title>Unsubscribed</title>"
            ."<div style='font-family:Arial,sans-serif;max-width:480px;margin:60px auto;padding:0 16px;text-align:center'><h2>You are unsubscribed</h2>"
            ."<p>You will not receive marketing messages from {$site} any more.</p></div>");
    }

    // ---------------------------------------------------------------- helpers

    private function draftOnly(Campaign $c): void
    {
        if ($c->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only a draft campaign can be changed or started.']);
        }
    }

    private function validated(Request $request, bool $preview = false): array
    {
        return $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => $preview ? 'nullable|string|max:255' : 'required|string|max:255',
            'channel' => ['required', Rule::in(Campaign::CHANNELS)],
            'subject' => [$preview ? 'nullable' : 'required_if:channel,email', 'nullable', 'string', 'max:255'],
            'message' => $preview ? 'nullable|string|max:5000' : 'required|string|max:5000',
            'audience' => 'nullable|array',
            'audience.type' => 'nullable|in:leads,customers,both',
            'audience.stages' => 'nullable|array',
            'audience.stages.*' => [Rule::in(Lead::STAGES)],
            'audience.sources' => 'nullable|array',
            'audience.sources.*' => [Rule::in(Lead::SOURCES)],
            'audience.customers' => 'nullable|in:all,with_project,completed_project',
            'audience.from' => 'nullable|date',
            'audience.to' => 'nullable|date',
        ]);
    }

    /**
     * People matching the filters: [{type, id, name, to}], deduplicated by phone/e-mail, opt-outs removed.
     * $branchId null = current branch context (preview); otherwise exactly that branch (when starting).
     */
    private function resolve(string $channel, array $a, ?int $branchId): array
    {
        $type = $a['type'] ?? 'customers';
        $field = $channel === 'sms' ? 'phone' : 'email';
        $scope = fn ($q) => $branchId ? $q->withoutGlobalScopes()->where('branch_id', $branchId) : $q;
        $dates = fn ($q) => $q->when(! empty($a['from']), fn ($x) => $x->whereDate('created_at', '>=', $a['from']))
            ->when(! empty($a['to']), fn ($x) => $x->whereDate('created_at', '<=', $a['to']));
        $people = [];

        if (in_array($type, ['customers', 'both'], true)) {
            $q = $scope(Customer::query())->where('marketing_opt_out', false)->whereNotNull($field)->where($field, '!=', '');
            $mode = $a['customers'] ?? 'all';
            if ($mode !== 'all') {
                $q->whereHas('projects', fn ($p) => $p->withoutGlobalScopes()->when($mode === 'completed_project', fn ($x) => $x->where('status', 'completed'))
                    ->when($mode === 'with_project', fn ($x) => $x->where('status', '!=', 'cancelled')));
            }
            foreach ($dates($q)->orderBy('id')->limit(20000)->get(['id', 'name', $field]) as $c) {
                $people[] = ['type' => 'customer', 'id' => $c->id, 'name' => $c->name, 'to' => $c->$field];
            }
        }
        if (in_array($type, ['leads', 'both'], true)) {
            $q = $scope(Lead::query())->where('marketing_opt_out', false)->whereNotNull($field)->where($field, '!=', '')
                ->when(! empty($a['stages']), fn ($x) => $x->whereIn('stage', $a['stages']))
                ->when(! empty($a['sources']), fn ($x) => $x->whereIn('source', $a['sources']));
            foreach ($dates($q)->orderBy('id')->limit(20000)->get(['id', 'name', $field]) as $l) {
                $people[] = ['type' => 'lead', 'id' => $l->id, 'name' => $l->name, 'to' => $l->$field];
            }
        }

        $seen = [];
        $out = [];
        foreach ($people as $p) {
            $to = $channel === 'sms' ? Sms::normalize($p['to']) : strtolower(trim($p['to']));
            $valid = $channel === 'sms' ? (bool) preg_match('/^8801[3-9]\d{8}$/', $to) : (bool) filter_var($to, FILTER_VALIDATE_EMAIL);
            if (! $valid || isset($seen[$to])) {
                continue;
            }
            $seen[$to] = true;
            $out[] = ['type' => $p['type'], 'id' => $p['id'], 'name' => $p['name'], 'to' => $to];
        }

        return $out;
    }
}
