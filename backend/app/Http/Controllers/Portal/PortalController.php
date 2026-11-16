<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Business\Customer;
use App\Models\Business\Invoice;
use App\Models\Business\Payment;
use App\Models\Business\ProjectFile;
use App\Models\Business\Ticket;
use App\Models\Business\TicketComment;
use App\Models\Business\WorkProject;
use App\Services\DocumentNumber;
use App\Services\Documents;
use App\Support\Notifier;
use App\Support\Uploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Client portal (/api/portal). A customer sees only their own projects, invoices, payments and tickets.
 * Every query is filtered by customer_id; there is no branch context here (BelongsToBranch adds no filter).
 * Drafts and cancelled documents are never shown; internal ticket notes are never shown.
 */
class PortalController extends Controller
{
    private const PROJECT_FIELDS = ['id', 'code', 'title', 'site_address', 'status', 'progress', 'start_date', 'end_date', 'handover_date', 'warranty_until', 'contract_value'];

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string|max:255', 'password' => 'required|string|max:255']);
        $login = trim($data['login']);
        $candidates = Customer::withoutGlobalScopes()->where('portal_enabled', true)->whereNotNull('portal_password')
            ->where(fn ($q) => $q->where('email', $login)->orWhere('phone', $login))->limit(5)->get();
        $customer = $candidates->first(fn ($c) => Hash::check($data['password'], $c->portal_password));
        if (! $customer) {
            if ($candidates->isEmpty()) {
                Hash::check($data['password'], '$2y$12$abcdefghijklmnopqrstuuJ2Yk7m1yQwz0b0Gm6r2b3n4o5p6q7r8'); // even timing
            }
            throw ValidationException::withMessages(['login' => 'Invalid phone/e-mail or password.']);
        }
        $customer->forceFill(['portal_last_login' => now()])->saveQuietly();

        return response()->json(['token' => $customer->createToken('client-portal')->plainTextToken, 'customer' => $this->profile($customer)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out']);
    }

    public function me(Request $request)
    {
        $c = $request->user();
        $invoices = Invoice::withoutGlobalScopes()->where('customer_id', $c->id)->whereNotIn('status', ['draft', 'cancelled'])->get();

        return response()->json([
            'customer' => $this->profile($c),
            'summary' => [
                'projects' => WorkProject::withoutGlobalScopes()->where('customer_id', $c->id)->where('status', '!=', 'cancelled')->count(),
                'invoiced' => round((float) $invoices->sum('total'), 2),
                'paid' => round((float) $invoices->sum('paid'), 2),
                'due' => round((float) $invoices->sum(fn ($i) => (float) $i->due), 2),
                'open_tickets' => Ticket::withoutGlobalScopes()->where('customer_id', $c->id)->whereIn('status', ['open', 'in_progress'])->count(),
            ],
        ]);
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => 'required|string|min:8|max:100|confirmed']);
        $c = $request->user();
        if (! Hash::check($data['current_password'], $c->portal_password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is wrong.']);
        }
        $c->forceFill(['portal_password' => Hash::make($data['password'])])->saveQuietly();
        $c->tokens()->where('id', '!=', $c->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed']);
    }

    // ---------------- Projects ----------------

    public function projects(Request $request)
    {
        return response()->json(['data' => WorkProject::withoutGlobalScopes()->where('customer_id', $request->user()->id)
            ->where('status', '!=', 'cancelled')->latest('id')->get(self::PROJECT_FIELDS)]);
    }

    public function project(Request $request, int $id)
    {
        $p = WorkProject::withoutGlobalScopes()->where('customer_id', $request->user()->id)->where('status', '!=', 'cancelled')
            ->findOrFail($id, self::PROJECT_FIELDS);

        return response()->json([
            'data' => $p,
            'milestones' => $p->milestones()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'progress', 'due_date']),
            'photos' => ProjectFile::where('work_project_id', $p->id)->where('kind', 'image')->latest('id')->limit(60)->get(['id', 'path', 'original_name', 'created_at']),
            'invoices' => $this->invoiceQuery($request)->where('work_project_id', $p->id)->get(),
        ]);
    }

    // ---------------- Billing ----------------

    private function invoiceQuery(Request $request)
    {
        return Invoice::withoutGlobalScopes()->where('customer_id', $request->user()->id)->whereNotIn('status', ['draft', 'cancelled'])
            ->with('project:id,code,title')->latest('date')->latest('id')
            ->select(['id', 'number', 'work_project_id', 'title', 'date', 'due_date', 'status', 'total', 'paid', 'credited']);
    }

    public function invoices(Request $request)
    {
        return response()->json(['data' => $this->invoiceQuery($request)->limit(200)->get()]);
    }

    public function invoicePdf(Request $request, int $id)
    {
        $inv = Invoice::withoutGlobalScopes()->where('customer_id', $request->user()->id)->whereNotIn('status', ['draft', 'cancelled'])->findOrFail($id);
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';

        return $this->pdf(Documents::invoice($inv, $lang), $lang);
    }

    public function payments(Request $request)
    {
        return response()->json(['data' => Payment::withoutGlobalScopes()->where('customer_id', $request->user()->id)->where('status', 'received')
            ->with('invoice:id,number')->latest('date')->latest('id')->limit(200)
            ->get(['id', 'number', 'invoice_id', 'method', 'amount', 'reference', 'date'])]);
    }

    public function receiptPdf(Request $request, int $id)
    {
        $p = Payment::withoutGlobalScopes()->where('customer_id', $request->user()->id)->where('status', 'received')->findOrFail($id);
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';

        return $this->pdf(Documents::receipt($p, $lang), $lang);
    }

    private function pdf(array $doc, string $lang)
    {
        $name = preg_replace('/[^A-Za-z0-9\-]+/', '-', $doc['number']).($lang === 'bn' ? '-bn' : '').'.pdf';

        return response(Documents::pdf($doc, $lang), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'"']);
    }

    // ---------------- Service tickets ----------------

    public function tickets(Request $request)
    {
        return response()->json(['data' => Ticket::withoutGlobalScopes()->where('customer_id', $request->user()->id)
            ->with('project:id,code,title')->latest('id')->limit(100)
            ->get(['id', 'number', 'work_project_id', 'subject', 'status', 'visit_at', 'created_at', 'resolved_at'])]);
    }

    public function ticket(Request $request, int $id)
    {
        $t = Ticket::withoutGlobalScopes()->where('customer_id', $request->user()->id)->with('project:id,code,title')
            ->findOrFail($id, ['id', 'number', 'work_project_id', 'subject', 'description', 'status', 'visit_at', 'warranty_until', 'created_at', 'resolved_at']);
        $comments = TicketComment::where('ticket_id', $t->id)->where(fn ($q) => $q->where('kind', 'client')->orWhere('is_public', true))
            ->with('user:id,name')->oldest('id')->get(['id', 'user_id', 'kind', 'body', 'attachment', 'created_at']);

        return response()->json(['data' => $t, 'comments' => $comments->map(fn ($c) => [
            'id' => $c->id, 'mine' => $c->kind === 'client', 'by' => $c->kind === 'client' ? 'You' : ($c->user?->name ?? 'Support team'),
            'body' => $c->body, 'attachment' => $c->attachment, 'created_at' => $c->created_at,
        ])]);
    }

    public function createTicket(Request $request)
    {
        $c = $request->user();
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'work_project_id' => 'nullable|integer',
            'photo' => 'nullable|file|image|max:8192',
        ]);
        $project = ! empty($data['work_project_id'])
            ? WorkProject::withoutGlobalScopes()->where('customer_id', $c->id)->findOrFail($data['work_project_id'])
            : WorkProject::withoutGlobalScopes()->where('customer_id', $c->id)->latest('id')->first();
        $branchId = $project?->branch_id ?? $c->branch_id;
        $photo = $request->hasFile('photo') ? Uploader::store($request->file('photo'), 'tickets') : null;

        $ticket = DB::transaction(function () use ($data, $c, $project, $branchId, $photo) {
            $t = Ticket::withoutGlobalScopes()->create([
                'branch_id' => $branchId, 'number' => DocumentNumber::next('TKT', $branchId),
                'customer_id' => $c->id, 'work_project_id' => $project?->id,
                'contact_name' => $c->name, 'contact_phone' => $c->phone, 'contact_email' => $c->email, 'address' => $project?->site_address ?? $c->address,
                'subject' => $data['subject'], 'description' => $data['description'] ?? null,
                'priority' => 'medium', 'source' => 'portal', 'status' => 'open', 'warranty_until' => $project?->warranty_until,
            ]);
            TicketComment::create(['ticket_id' => $t->id, 'kind' => 'system', 'body' => 'Opened by the customer from the client portal.']);
            if ($photo) {
                TicketComment::create(['ticket_id' => $t->id, 'kind' => 'client', 'body' => 'Photo', 'attachment' => $photo]);
            }

            return $t;
        });
        $this->notifyStaff($ticket, "New service request {$ticket->number} from {$c->name} (client portal)");

        return response()->json(['message' => "Request {$ticket->number} received. Our team will contact you soon.", 'data' => ['id' => $ticket->id, 'number' => $ticket->number]], 201);
    }

    public function comment(Request $request, int $id)
    {
        $t = Ticket::withoutGlobalScopes()->where('customer_id', $request->user()->id)->findOrFail($id);
        if ($t->status === 'closed') {
            throw ValidationException::withMessages(['body' => 'This request is closed. Please open a new one.']);
        }
        $data = $request->validate(['body' => 'required_without:photo|nullable|string|max:5000', 'photo' => 'nullable|file|image|max:8192']);
        TicketComment::create([
            'ticket_id' => $t->id, 'kind' => 'client', 'body' => $data['body'] ?? 'Photo',
            'attachment' => $request->hasFile('photo') ? Uploader::store($request->file('photo'), 'tickets') : null,
        ]);
        if ($t->status === 'resolved') {
            $t->update(['status' => 'in_progress', 'resolved_at' => null]);
            TicketComment::create(['ticket_id' => $t->id, 'kind' => 'system', 'body' => 'Reopened: the customer replied from the client portal.']);
        }
        $this->notifyStaff($t, "Customer replied on {$t->number} (client portal)");

        return response()->json(['message' => 'Message sent'], 201);
    }

    private function notifyStaff(Ticket $t, string $subject): void
    {
        try {
            if ($to = Notifier::recipients()) {
                $url = Notifier::adminUrl('/biz/tickets/'.$t->id);
                Mail::raw("{$subject}\n\nSubject: {$t->subject}\nOpen: {$url}", fn ($m) => $m->to($to)->subject($subject.' | '.Notifier::siteName()));
            }
        } catch (\Throwable $e) {
            Log::error('Portal ticket e-mail failed: '.$e->getMessage());
        }
    }

    private function profile(Customer $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email, 'company' => $c->company, 'address' => $c->address];
    }
}
