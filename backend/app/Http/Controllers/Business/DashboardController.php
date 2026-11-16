<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Branch;
use App\Models\Business\Invoice;
use App\Models\Business\Item;
use App\Models\Business\Lead;
use App\Models\Business\LeadActivity;
use App\Models\Business\Payment;
use App\Models\Business\ProjectTask;
use App\Models\Business\Quotation;
use App\Models\Business\StockBalance;
use App\Models\Business\Store;
use App\Models\Business\Ticket;
use App\Models\Business\Transaction;
use App\Models\Business\WorkProject;
use App\Support\BranchContext;
use Illuminate\Http\Request;

/**
 * Role-based business dashboard: every block is included only when the user has the
 * permission for it, and every number is branch-scoped (or all branches in central view).
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $me = $u->id;
        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();
        $out = ['month' => now()->format('F Y'), 'central' => BranchContext::isAll()];

        if ($u->hasPermission('crm.view')) {
            $won = Lead::where('stage', 'won')->where('won_at', '>=', $monthStart)->count();
            $lost = Lead::where('stage', 'lost')->where('updated_at', '>=', $monthStart)->count();
            $out['crm'] = [
                'new_leads' => Lead::where('created_at', '>=', $monthStart)->count(),
                'open_leads' => Lead::whereNotIn('stage', ['won', 'lost'])->count(),
                'pipeline_value' => round((float) Lead::whereNotIn('stage', ['won', 'lost'])->sum('expected_value'), 2),
                'won' => $won,
                'conversion' => $won + $lost ? round($won * 100 / ($won + $lost)) : null,
                'by_stage' => Lead::whereNotIn('stage', ['won', 'lost'])->selectRaw('stage, COUNT(*) c')->groupBy('stage')->pluck('c', 'stage'),
                'my_followups' => LeadActivity::whereNull('done_at')->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay())
                    ->whereHas('lead', fn ($l) => $l->whereNotIn('stage', ['won', 'lost'])->where(fn ($w) => $w->where('assigned_to', $me)))
                    ->with('lead:id,name,phone')->orderBy('follow_up_at')->limit(8)->get(['id', 'lead_id', 'follow_up_at', 'note']),
            ];
        }
        if ($u->hasPermission('quotations.view')) {
            $out['quotations'] = [
                'sent' => Quotation::where('sent_at', '>=', $monthStart)->count(),
                'approved' => Quotation::where('status', 'approved')->where('approved_at', '>=', $monthStart)->count(),
                'approved_value' => round((float) Quotation::where('status', 'approved')->where('approved_at', '>=', $monthStart)->sum('total'), 2),
                'pending' => Quotation::where('status', 'sent')->count(),
            ];
        }
        if ($u->hasPermission('billing.view')) {
            $open = Invoice::whereIn('status', ['issued', 'partial'])->get(['id', 'total', 'paid', 'credited', 'due_date', 'status']);
            $overdue = $open->filter(fn ($i) => $i->is_overdue);
            $trend = [];
            for ($m = 5; $m >= 0; $m--) {
                $s = now()->subMonths($m)->startOfMonth();
                $trend[] = [
                    'month' => $s->format('M'),
                    'invoiced' => round((float) Invoice::whereNotIn('status', ['draft', 'cancelled'])->whereBetween('date', [$s->toDateString(), $s->copy()->endOfMonth()->toDateString()])->sum('total'), 2),
                    'collected' => round((float) Payment::where('status', 'received')->whereBetween('date', [$s->toDateString(), $s->copy()->endOfMonth()->toDateString()])->sum('amount'), 2),
                ];
            }
            $out['billing'] = [
                'invoiced' => round((float) Invoice::whereNotIn('status', ['draft', 'cancelled'])->where('date', '>=', $monthStart)->sum('total'), 2),
                'collected' => round((float) Payment::where('status', 'received')->where('date', '>=', $monthStart)->sum('amount'), 2),
                'outstanding' => round($open->sum(fn ($i) => (float) $i->due), 2),
                'overdue_count' => $overdue->count(),
                'overdue_amount' => round($overdue->sum(fn ($i) => (float) $i->due), 2),
                'trend' => $trend,
            ];
        }
        if ($u->hasPermission('accounts.view')) {
            $out['accounts'] = [
                'cash_bank' => round(Account::where('is_active', true)->get()->sum(fn ($a) => $a->balance()), 2),
                'income' => round((float) Transaction::where('status', 'active')->where('type', 'income')->where('date', '>=', $monthStart)->sum('amount'), 2),
                'expense' => round((float) Transaction::where('status', 'active')->where('type', 'expense')->where('date', '>=', $monthStart)->sum('amount'), 2),
            ];
        }
        if ($u->hasPermission('projects.view')) {
            $out['projects'] = [
                'running' => WorkProject::where('status', 'running')->count(),
                'planning' => WorkProject::where('status', 'planning')->count(),
                'completed_month' => WorkProject::where('status', 'completed')->where('updated_at', '>=', $monthStart)->count(),
                'list' => WorkProject::whereIn('status', ['running', 'planning'])->with('customer:id,name')->orderByRaw("FIELD(status, 'running', 'planning')")->latest('id')->limit(6)
                    ->get(['id', 'code', 'title', 'status', 'progress', 'end_date', 'customer_id']),
                'my_tasks' => ProjectTask::where('assigned_to', $me)->where('status', '!=', 'done')
                    ->whereHas('project', fn ($p) => $p) // keeps the branch scope of the project
                    ->with('project:id,code')->orderByRaw('due_date IS NULL, due_date')->limit(8)->get(['id', 'work_project_id', 'title', 'due_date', 'status']),
            ];
        }
        if ($u->hasPermission('inventory.view')) {
            $storeIds = Store::pluck('id');
            $qty = StockBalance::whereIn('store_id', $storeIds)->selectRaw('item_id, SUM(qty) q')->groupBy('item_id')->pluck('q', 'item_id');
            $items = Item::where('is_active', true)->get(['id', 'name', 'unit', 'min_stock', 'avg_cost']);
            $low = $items->filter(fn ($i) => (float) $i->min_stock > 0 && (float) ($qty[$i->id] ?? 0) <= (float) $i->min_stock)->values();
            $out['inventory'] = [
                'stock_value' => round($items->sum(fn ($i) => (float) ($qty[$i->id] ?? 0) * (float) $i->avg_cost), 2),
                'low_count' => $low->count(),
                'low' => $low->take(6)->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit, 'qty' => (float) ($qty[$i->id] ?? 0), 'min' => (float) $i->min_stock])->values(),
            ];
        }
        if ($u->hasPermission('tickets.view')) {
            $out['tickets'] = [
                'open' => Ticket::where('status', 'open')->count(),
                'in_progress' => Ticket::where('status', 'in_progress')->count(),
                'urgent' => Ticket::whereIn('status', ['open', 'in_progress'])->whereIn('priority', ['urgent', 'high'])->count(),
                'unassigned' => Ticket::whereIn('status', ['open', 'in_progress'])->whereNull('assigned_to')->count(),
                'mine' => Ticket::whereIn('status', ['open', 'in_progress'])->where('assigned_to', $me)->orderByRaw('visit_at IS NULL, visit_at')->limit(6)
                    ->get(['id', 'number', 'subject', 'priority', 'status', 'visit_at']),
            ];
        }

        // Central view: compare every branch this month
        if (BranchContext::isAll() && $u->hasPermission('branches.all')) {
            $out['branches'] = Branch::whereIn('id', BranchContext::allowed())->orderBy('id')->get()->map(function ($b) use ($monthStart) {
                $won = Lead::withoutGlobalScopes()->where('branch_id', $b->id)->where('stage', 'won')->where('won_at', '>=', $monthStart)->count();
                $lost = Lead::withoutGlobalScopes()->where('branch_id', $b->id)->where('stage', 'lost')->where('updated_at', '>=', $monthStart)->count();

                return [
                    'name' => $b->name, 'code' => $b->code,
                    'leads' => Lead::withoutGlobalScopes()->where('branch_id', $b->id)->where('created_at', '>=', $monthStart)->count(),
                    'conversion' => $won + $lost ? round($won * 100 / ($won + $lost)) : null,
                    'sales' => round((float) Invoice::withoutGlobalScopes()->where('branch_id', $b->id)->whereNotIn('status', ['draft', 'cancelled'])->where('date', '>=', $monthStart)->sum('total'), 2),
                    'collected' => round((float) Payment::withoutGlobalScopes()->where('branch_id', $b->id)->where('status', 'received')->where('date', '>=', $monthStart)->sum('amount'), 2),
                    'expense' => round((float) Transaction::withoutGlobalScopes()->where('branch_id', $b->id)->where('status', 'active')->where('type', 'expense')->where('date', '>=', $monthStart)->sum('amount'), 2),
                    'running_projects' => WorkProject::withoutGlobalScopes()->where('branch_id', $b->id)->where('status', 'running')->count(),
                    'open_tickets' => Ticket::withoutGlobalScopes()->where('branch_id', $b->id)->whereIn('status', ['open', 'in_progress'])->count(),
                ];
            });
        }

        return response()->json($out);
    }
}
