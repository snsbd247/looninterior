<?php

namespace App\Console\Commands;

use App\Models\Business\Invoice;
use App\Models\Business\LeadActivity;
use App\Models\User;
use App\Services\Money;
use App\Support\Notifier;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Daily digest e-mails (run by the scheduler, see routes/console.php):
 *  - each staff member: their follow-ups due today / overdue
 *  - notification e-mail (Settings → Notifications): overdue invoices
 * Runs outside any branch context, so it sees every branch.
 */
class BusinessReminders extends Command
{
    protected $signature = 'business:reminders {--dry : Only print what would be sent}';

    protected $description = 'E-mail today\'s follow-ups to staff and overdue invoices to the admin';

    public function handle(): int
    {
        $site = Notifier::siteName();
        $sent = 0;

        // 1) Follow-ups per responsible user (lead owner, else the person who scheduled it)
        $due = LeadActivity::query()->whereNull('done_at')->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now()->endOfDay())
            ->whereHas('lead', fn ($l) => $l->withoutGlobalScopes()->whereNotIn('stage', ['won', 'lost']))
            ->with(['lead' => fn ($l) => $l->withoutGlobalScopes()->select('id', 'name', 'phone', 'assigned_to', 'stage')])
            ->get()
            ->groupBy(fn ($a) => $a->lead->assigned_to ?: $a->user_id);

        foreach ($due as $userId => $items) {
            $user = User::where('id', $userId)->where('is_active', true)->first();
            if (! $user || ! $user->email) {
                continue;
            }
            $rows = $items->map(fn ($a) => [
                $a->follow_up_at->format('d M, h:i A').($a->follow_up_at->isPast() && ! $a->follow_up_at->isToday() ? ' (overdue)' : ''),
                $a->lead->name.($a->lead->phone ? ' · '.$a->lead->phone : ''),
                (string) $a->note,
            ])->all();
            $this->line("Follow-ups → {$user->email}: ".count($rows));
            if (! $this->option('dry')) {
                $this->send($user->email, "Your follow-ups for today ({$items->count()}) | {$site}", 'Your follow-ups', ['When', 'Lead', 'Note'], $rows, $site);
                $sent++;
            }
        }

        // 2) Overdue invoices to the notification address
        $overdue = Invoice::withoutGlobalScopes()->with(['customer' => fn ($c) => $c->withoutGlobalScopes()])
            ->whereIn('status', ['issued', 'partial'])->whereDate('due_date', '<', today())->orderBy('due_date')->get()
            ->filter(fn ($i) => (float) $i->due > 0);
        $to = Notifier::recipients();
        if ($overdue->isNotEmpty() && $to) {
            $rows = $overdue->map(fn ($i) => [
                $i->number, (string) $i->customer?->name.($i->customer?->phone ? ' · '.$i->customer->phone : ''),
                $i->due_date->format('d M Y').' ('.$i->due_date->diffInDays(today()).' days)', Money::format($i->due, 'en', true),
            ])->values()->all();
            $total = Money::format($overdue->sum(fn ($i) => (float) $i->due), 'en', true);
            $this->line('Overdue invoices → '.implode(', ', $to).': '.count($rows));
            if (! $this->option('dry')) {
                $this->send($to, "Overdue invoices: {$total} | {$site}", "Overdue invoices (total {$total})", ['Invoice', 'Customer', 'Due date', 'Due'], $rows, $site);
                $sent++;
            }
        }

        $this->info("Done. {$sent} e-mail(s) sent.");

        return self::SUCCESS;
    }

    private function send(string|array $to, string $subject, string $title, array $head, array $rows, string $site): void
    {
        try {
            Mail::send('emails.digest', compact('title', 'head', 'rows', 'site') + ['url' => Notifier::adminUrl()], fn (Message $m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            $this->error('Mail failed: '.$e->getMessage());
        }
    }
}
