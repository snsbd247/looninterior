<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Customer;
use App\Models\Business\Ticket;
use App\Models\Business\TicketComment;
use App\Models\Business\WorkProject;
use App\Services\DocumentNumber;
use App\Support\BranchContext;
use App\Support\Uploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $q = Ticket::query()->with(['customer:id,name,phone', 'project:id,code', 'assignee:id,name', 'branch:id,code']);
        if ($request->query('status') === 'active') {
            $q->whereIn('status', ['open', 'in_progress']);
        } elseif ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        foreach (['priority', 'customer_id', 'work_project_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('assigned_to')) {
            $q->where('assigned_to', $request->query('assigned_to') === 'me' ? $request->user()->id : $request->query('assigned_to'));
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('subject', 'like', "%{$s}%")->orWhere('contact_phone', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        $q->orderByRaw("FIELD(status, 'open', 'in_progress', 'resolved', 'closed')")
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")->latest('id');

        $counts = Ticket::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return response()->json(array_merge($q->paginate(min(100, max(1, (int) $request->query('per_page', 20))))->toArray(), ['counts' => $counts]));
    }

    public function show(Ticket $ticket)
    {
        return response()->json(['data' => $ticket->load(['customer', 'project:id,code,title,warranty_until,handover_date', 'assignee:id,name', 'creator:id,name', 'branch:id,name,code', 'comments.user:id,name'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'work_project_id' => 'nullable|integer|exists:work_projects,id',
            'contact_name' => 'nullable|required_without:customer_id|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'source' => ['nullable', Rule::in(Ticket::SOURCES)],
            'assigned_to' => 'nullable|integer|exists:users,id',
            'visit_at' => 'nullable|date',
        ]);
        $customer = ! empty($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : null;
        $project = ! empty($data['work_project_id']) ? WorkProject::findOrFail($data['work_project_id']) : null;
        if ($project && $customer && (int) $project->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages(['work_project_id' => 'This project belongs to another customer.']);
        }
        $branchId = $project?->branch_id ?? $customer?->branch_id ?? BranchContext::forWrite() ?? ($data['branch_id'] ?? null);
        if (! $branchId) {
            throw ValidationException::withMessages(['branch_id' => 'Choose a branch.']);
        }

        $ticket = DB::transaction(function () use ($data, $customer, $project, $branchId, $request) {
            $t = Ticket::create([
                'branch_id' => $branchId,
                'number' => DocumentNumber::next('TKT', $branchId),
                'customer_id' => $customer?->id ?? $project?->customer_id,
                'work_project_id' => $project?->id,
                'contact_name' => $data['contact_name'] ?? $customer?->name,
                'contact_phone' => $data['contact_phone'] ?? $customer?->phone,
                'contact_email' => $data['contact_email'] ?? $customer?->email,
                'address' => $data['address'] ?? $project?->site_address ?? $customer?->address,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
                'source' => $data['source'] ?? 'phone',
                'status' => 'open',
                'assigned_to' => $data['assigned_to'] ?? null,
                'visit_at' => $data['visit_at'] ?? null,
                'warranty_until' => $project?->warranty_until,
                'created_by' => $request->user()->id,
            ]);
            TicketComment::create(['ticket_id' => $t->id, 'user_id' => $request->user()->id, 'kind' => 'system', 'body' => 'Ticket opened ('.str_replace('_', ' ', $t->source).').']);

            return $t;
        });

        return response()->json(['message' => "Ticket {$ticket->number} opened", 'data' => $ticket], 201);
    }

    /** Assign, priority, visit date, project link and status (each change goes to the timeline). */
    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Ticket::STATUSES)],
            'priority' => ['sometimes', Rule::in(Ticket::PRIORITIES)],
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
            'visit_at' => 'sometimes|nullable|date',
            'work_project_id' => 'sometimes|nullable|integer|exists:work_projects,id',
            'subject' => 'sometimes|required|string|max:255',
            'note' => 'nullable|string|max:3000',
        ]);
        DB::transaction(function () use ($ticket, $data, $request) {
            $changes = [];
            if (array_key_exists('work_project_id', $data) && (int) $data['work_project_id'] !== (int) $ticket->work_project_id) {
                $project = $data['work_project_id'] ? WorkProject::findOrFail($data['work_project_id']) : null;
                $ticket->work_project_id = $project?->id;
                $ticket->warranty_until = $project?->warranty_until;
                if ($project && ! $ticket->customer_id) {
                    $ticket->customer_id = $project->customer_id;
                }
                $changes[] = 'project '.($project?->code ?? 'removed');
            }
            if (isset($data['status']) && $data['status'] !== $ticket->status) {
                $changes[] = 'status '.str_replace('_', ' ', $ticket->status).' → '.str_replace('_', ' ', $data['status']);
                $ticket->status = $data['status'];
                $ticket->resolved_at = in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null;
                $ticket->closed_at = $data['status'] === 'closed' ? now() : null;
            }
            if (isset($data['priority']) && $data['priority'] !== $ticket->priority) {
                $changes[] = "priority {$data['priority']}";
                $ticket->priority = $data['priority'];
            }
            if (array_key_exists('assigned_to', $data) && (int) $data['assigned_to'] !== (int) $ticket->assigned_to) {
                $ticket->assigned_to = $data['assigned_to'];
                $changes[] = 'assigned to '.(\App\Models\User::whereKey($data['assigned_to'])->value('name') ?? 'nobody');
            }
            if (array_key_exists('visit_at', $data) && $data['visit_at'] != $ticket->visit_at?->format('Y-m-d\TH:i')) {
                $ticket->visit_at = $data['visit_at'];
                $changes[] = 'visit '.($data['visit_at'] ? date('d M Y, h:i A', strtotime($data['visit_at'])) : 'removed');
            }
            if (isset($data['subject'])) {
                $ticket->subject = $data['subject'];
            }
            $ticket->save();
            if ($changes || ! empty($data['note'])) {
                TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'kind' => 'status',
                    'body' => trim(($changes ? ucfirst(implode(', ', $changes)).'.' : '')."\n".($data['note'] ?? ''))]);
            }
        });

        return response()->json(['message' => 'Ticket updated', 'data' => $ticket->fresh(['assignee:id,name'])]);
    }

    public function comment(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'body' => 'required_without:photo|nullable|string|max:5000',
            'photo' => 'nullable|file|image|max:8192',
            'is_public' => 'nullable|boolean',
        ]);
        $c = TicketComment::create([
            'ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'kind' => 'comment',
            'is_public' => (bool) ($data['is_public'] ?? false), // shown to the customer in the client portal
            'body' => $data['body'] ?? 'Photo',
            'attachment' => $request->hasFile('photo') ? Uploader::store($request->file('photo'), 'tickets') : null,
        ]);
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json(['message' => 'Comment added', 'data' => $c->load('user:id,name')], 201);
    }

    /** Service performance: open counts, average resolution time, by priority and source. */
    public function report(Request $request)
    {
        $from = $request->query('from') ?: now()->subDays(90)->toDateString();
        $to = $request->query('to') ?: now()->toDateString();
        $rows = Ticket::query()->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->get();
        $resolved = $rows->whereNotNull('resolved_at');
        $hours = fn ($set) => $set->count() ? round($set->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60), 1) : null;

        return response()->json([
            'from' => $from, 'to' => $to,
            'total' => $rows->count(),
            'open' => $rows->whereIn('status', ['open', 'in_progress'])->count(),
            'resolved' => $resolved->count(),
            'avg_resolution_hours' => $hours($resolved),
            'in_warranty' => $rows->filter(fn ($t) => $t->in_warranty === true)->count(),
            'by_priority' => collect(Ticket::PRIORITIES)->mapWithKeys(fn ($p) => [$p => [
                'total' => $rows->where('priority', $p)->count(),
                'open' => $rows->where('priority', $p)->whereIn('status', ['open', 'in_progress'])->count(),
                'avg_hours' => $hours($resolved->where('priority', $p)),
            ]]),
            'by_source' => $rows->groupBy('source')->map->count(),
        ]);
    }
}
