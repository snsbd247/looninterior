<?php

namespace App\Services;

use App\Models\Business\Account;
use App\Models\Business\CreditNote;
use App\Models\Business\Invoice;
use App\Models\Business\Payment;
use App\Models\Business\TransactionCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoice money rules. Every method runs in one DB transaction and locks the
 * invoice row, so two people receiving money at once can never over-collect.
 */
class Billing
{
    public static function receive(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $inv = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
            if (! in_array($inv->status, ['issued', 'partial'], true)) {
                throw ValidationException::withMessages(['amount' => $inv->status === 'draft'
                    ? 'Issue the invoice before receiving payment.'
                    : "This invoice is {$inv->status}."]);
            }
            $amount = round((float) $data['amount'], 2);
            $due = round((float) $inv->total - (float) $inv->paid - (float) $inv->credited, 2);
            if ($amount <= 0 || $amount > $due) {
                throw ValidationException::withMessages(['amount' => 'Amount must be between 0.01 and the due amount ('.Money::format($due).').']);
            }
            $account = Account::withoutGlobalScopes()->findOrFail($data['account_id']);
            if ((int) $account->branch_id !== (int) $inv->branch_id) {
                throw ValidationException::withMessages(['account_id' => 'Choose an account of the invoice\'s branch.']);
            }

            $payment = Payment::create([
                'branch_id' => $inv->branch_id,
                'number' => DocumentNumber::next('RCV', $inv->branch_id),
                'invoice_id' => $inv->id,
                'customer_id' => $inv->customer_id,
                'account_id' => $account->id,
                'method' => $data['method'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'received',
                'created_by' => Auth::id(),
            ]);

            Ledger::post([
                'type' => 'income', 'account_id' => $account->id, 'amount' => $amount, 'date' => $data['date'],
                'category_id' => TransactionCategory::idFor('customer_payment'),
                'work_project_id' => $inv->work_project_id,
                'description' => "Payment {$payment->number} for invoice {$inv->number}",
                'reference' => $data['reference'] ?? null,
            ], $payment, $inv->customer);

            $inv->paid = round((float) $inv->paid + $amount, 2);
            $inv->refreshPaymentStatus();
            $inv->save();

            return $payment;
        });
    }

    public static function cancelPayment(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $p = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            if ($p->status === 'cancelled') {
                return;
            }
            $inv = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($p->invoice_id);
            $p->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            Ledger::cancelFor($p, "Payment {$p->number} cancelled: {$reason}");
            $inv->paid = round(max(0, (float) $inv->paid - (float) $p->amount), 2);
            $inv->refreshPaymentStatus();
            $inv->save();
        });
    }

    public static function creditNote(Invoice $invoice, float|string $amount, string $reason, string $date): CreditNote
    {
        return DB::transaction(function () use ($invoice, $amount, $reason, $date) {
            $inv = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
            if (! in_array($inv->status, ['issued', 'partial'], true)) {
                throw ValidationException::withMessages(['amount' => 'Credit notes can only be given on issued, unpaid invoices.']);
            }
            $amount = round((float) $amount, 2);
            $due = round((float) $inv->total - (float) $inv->paid - (float) $inv->credited, 2);
            if ($amount <= 0 || $amount > $due) {
                throw ValidationException::withMessages(['amount' => 'Credit cannot be more than the due amount ('.Money::format($due).').']);
            }
            $cn = CreditNote::create([
                'branch_id' => $inv->branch_id,
                'number' => DocumentNumber::next('CN', $inv->branch_id),
                'invoice_id' => $inv->id, 'amount' => $amount, 'reason' => $reason, 'date' => $date,
                'created_by' => Auth::id(),
            ]);
            $inv->credited = round((float) $inv->credited + $amount, 2);
            $inv->refreshPaymentStatus();
            $inv->save();

            return $cn;
        });
    }

    public static function cancelInvoice(Invoice $invoice, string $reason): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $inv = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
            if ((float) $inv->paid > 0) {
                throw ValidationException::withMessages(['status' => 'This invoice has payments. Cancel the payments first (or give a credit note).']);
            }
            if ($inv->status === 'cancelled') {
                return;
            }
            $inv->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            \App\Models\Business\PaymentSchedule::where('invoice_id', $inv->id)->update(['invoice_id' => null]);
        });
    }
}
