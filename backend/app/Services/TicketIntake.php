<?php

namespace App\Services;

use App\Models\Business\Customer;
use App\Models\Business\Ticket;
use App\Models\Business\TicketComment;
use App\Models\Business\WorkProject;
use App\Support\Notifier;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Website "Service Request" → ticket. Matches the customer by phone (and their latest project),
 * otherwise uses the CRM default branch. E-mails the ticket number to the client and the admin.
 * A mail failure never breaks the form.
 */
class TicketIntake
{
    public static function fromWebsite(array $data): Ticket
    {
        $phone = trim((string) $data['phone']);
        $customer = Customer::withoutGlobalScopes()->where('phone', $phone)->latest('id')->first();
        $project = $customer ? WorkProject::withoutGlobalScopes()->where('customer_id', $customer->id)->latest('id')->first() : null;
        $branchId = $project?->branch_id ?? $customer?->branch_id ?? LeadIntake::defaultBranchId();

        $ticket = DB::transaction(function () use ($data, $customer, $project, $branchId) {
            $t = Ticket::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'number' => DocumentNumber::next('TKT', $branchId),
                'customer_id' => $customer?->id,
                'work_project_id' => $project?->id,
                'contact_name' => $data['name'],
                'contact_phone' => $data['phone'],
                'contact_email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'priority' => 'medium',
                'source' => 'website',
                'status' => 'open',
                'warranty_until' => $project?->warranty_until,
            ]);
            TicketComment::create(['ticket_id' => $t->id, 'kind' => 'system',
                'body' => 'Service request from the website.'.($customer ? " Matched customer {$customer->name}".($project ? " / {$project->code}" : '').'.' : '')]);

            return $t;
        });

        $site = Notifier::siteName();
        try {
            if ($ticket->contact_email) {
                Mail::send('emails.ticket', ['site' => $site, 't' => $ticket, 'forClient' => true],
                    fn (Message $m) => $m->to($ticket->contact_email)->subject("Service request received: {$ticket->number} | {$site}"));
            }
            if ($to = Notifier::recipients()) {
                Mail::send('emails.ticket', ['site' => $site, 't' => $ticket, 'forClient' => false, 'url' => Notifier::adminUrl('/biz/tickets/'.$ticket->id)],
                    fn (Message $m) => $m->to($to)->subject("New service request {$ticket->number} from {$ticket->contact_name} | {$site}"));
            }
        } catch (\Throwable $e) {
            Log::error('Ticket e-mail failed: '.$e->getMessage());
        }

        return $ticket;
    }
}
