<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Invoice;
use App\Models\Business\LeadActivity;
use App\Models\Business\ProjectMilestone;
use App\Models\Business\ProjectTask;
use App\Models\Business\PurchaseOrder;
use App\Models\Business\Ticket;
use App\Models\Business\WorkProject;
use Illuminate\Http\Request;

/**
 * One calendar for everything with a date: follow-ups, site visits (tickets), task and milestone
 * deadlines, project handover targets, invoice due dates and expected deliveries.
 * Each source is included only when the user has its permission; all branch-scoped.
 * ?mine=1 limits to things assigned to the current user.
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth()->subDays(7);
        $to = $request->date('to') ?? now()->endOfMonth()->addDays(7);
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $u = $request->user();
        $mine = $request->boolean('mine');
        $events = collect();

        if ($u->hasPermission('crm.view')) {
            LeadActivity::whereNull('done_at')->whereBetween('follow_up_at', [$from, $to])
                ->whereHas('lead', fn ($l) => $l->whereNotIn('stage', ['won', 'lost'])->when($mine, fn ($q) => $q->where('assigned_to', $u->id)))
                ->with('lead:id,name')->get()
                ->each(fn ($a) => $events->push($this->ev('followup', $a->follow_up_at, 'Follow-up: '.$a->lead?->name, "/admin/biz/leads/{$a->lead_id}", $a->note, true)));
        }
        if ($u->hasPermission('tickets.view')) {
            Ticket::whereIn('status', ['open', 'in_progress'])->whereBetween('visit_at', [$from, $to])
                ->when($mine, fn ($q) => $q->where('assigned_to', $u->id))->get()
                ->each(fn ($t) => $events->push($this->ev('visit', $t->visit_at, "Visit {$t->number}: {$t->subject}", "/admin/biz/tickets/{$t->id}", $t->address, true)));
        }
        if ($u->hasPermission('projects.view')) {
            ProjectTask::where('status', '!=', 'done')->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
                ->when($mine, fn ($q) => $q->where('assigned_to', $u->id))
                ->whereHas('project', fn ($p) => $p)->with('project:id,code')->get()
                ->each(fn ($t) => $events->push($this->ev('task', $t->due_date, "Task: {$t->title}", "/admin/biz/projects/{$t->work_project_id}?tab=work", $t->project?->code)));
            if (! $mine) {
                ProjectMilestone::where('progress', '<', 100)->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
                    ->whereHas('project', fn ($p) => $p->whereIn('status', ['planning', 'running']))->with('project:id,code')->get()
                    ->each(fn ($m) => $events->push($this->ev('milestone', $m->due_date, "{$m->project?->code}: {$m->name}", "/admin/biz/projects/{$m->work_project_id}?tab=work", "{$m->progress}% done")));
                WorkProject::whereIn('status', ['planning', 'running'])->whereBetween('end_date', [$from->toDateString(), $to->toDateString()])->get()
                    ->each(fn ($p) => $events->push($this->ev('handover', $p->end_date, "Handover target: {$p->title}", "/admin/biz/projects/{$p->id}", $p->code)));
            }
        }
        if (! $mine && $u->hasPermission('billing.view')) {
            Invoice::whereIn('status', ['issued', 'partial'])->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->with('customer:id,name')->get()
                ->each(fn ($i) => $events->push($this->ev('invoice', $i->due_date, "Invoice due {$i->number}", "/admin/biz/invoices/{$i->id}", $i->customer?->name.' · '.number_format((float) $i->due, 2))));
        }
        if (! $mine && $u->hasPermission('inventory.view')) {
            PurchaseOrder::whereIn('status', ['sent', 'partial'])->whereBetween('expected_date', [$from->toDateString(), $to->toDateString()])->with('supplier:id,name')->get()
                ->each(fn ($o) => $events->push($this->ev('delivery', $o->expected_date, "Delivery {$o->number}", "/admin/biz/purchase-orders/{$o->id}", $o->supplier?->name)));
        }

        return response()->json(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'data' => $events->sortBy('start')->values()]);
    }

    private function ev(string $type, $when, string $title, string $link, ?string $detail = null, bool $hasTime = false): array
    {
        $dt = $when instanceof \DateTimeInterface ? $when : \Illuminate\Support\Carbon::parse($when);

        return [
            'type' => $type,
            'date' => $dt->format('Y-m-d'),
            'time' => $hasTime ? $dt->format('H:i') : null,
            'start' => $dt->format('Y-m-d').' '.($hasTime ? $dt->format('H:i') : '00:00'),
            'title' => $title,
            'detail' => $detail,
            'link' => $link,
        ];
    }
}
