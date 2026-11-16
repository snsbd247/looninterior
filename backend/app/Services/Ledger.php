<?php

namespace App\Services;

use App\Models\Business\Account;
use App\Models\Business\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only way money enters or leaves an account. Call inside the caller's DB::transaction.
 * Rows are never deleted; cancel() marks them cancelled (excluded from every balance/report).
 */
class Ledger
{
    /**
     * @param array{type:string, account_id:int, amount:float|string, date:string, category_id?:?int,
     *              work_project_id?:?int, description?:?string, reference?:?string, attachment?:?string,
     *              transfer_group?:?string} $data
     */
    public static function post(array $data, ?Model $source = null, ?Model $party = null): Transaction
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be more than zero.']);
        }
        $account = Account::withoutGlobalScopes()->lockForUpdate()->findOrFail($data['account_id']);
        if (! $account->is_active) {
            throw ValidationException::withMessages(['account_id' => "Account \"{$account->name}\" is inactive."]);
        }

        return Transaction::create([
            'branch_id' => $account->branch_id,
            'date' => $data['date'],
            'type' => $data['type'],
            'account_id' => $account->id,
            'category_id' => $data['category_id'] ?? null,
            'amount' => $amount,
            'work_project_id' => $data['work_project_id'] ?? null,
            'party_type' => $party?->getMorphClass(),
            'party_id' => $party?->getKey(),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'attachment' => $data['attachment'] ?? null,
            'transfer_group' => $data['transfer_group'] ?? null,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);
    }

    /** Move money between two accounts (may be in different branches). */
    public static function transfer(int $fromId, int $toId, float|string $amount, string $date, ?string $note = null, ?string $reference = null): array
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages(['to_account_id' => 'Choose a different account.']);
        }

        return DB::transaction(function () use ($fromId, $toId, $amount, $date, $note, $reference) {
            $group = (string) Str::uuid();
            $from = Account::withoutGlobalScopes()->findOrFail($fromId);
            $to = Account::withoutGlobalScopes()->findOrFail($toId);
            $out = self::post(['type' => 'transfer_out', 'account_id' => $fromId, 'amount' => $amount, 'date' => $date,
                'description' => trim("Transfer to {$to->name}".($note ? " · {$note}" : '')), 'reference' => $reference, 'transfer_group' => $group]);
            $in = self::post(['type' => 'transfer_in', 'account_id' => $toId, 'amount' => $amount, 'date' => $date,
                'description' => trim("Transfer from {$from->name}".($note ? " · {$note}" : '')), 'reference' => $reference, 'transfer_group' => $group]);

            return [$out, $in];
        });
    }

    /** Cancel a transaction (and its transfer pair). */
    public static function cancel(Transaction $t, string $reason): void
    {
        $rows = $t->transfer_group
            ? Transaction::withoutGlobalScopes()->where('transfer_group', $t->transfer_group)->get()
            : collect([$t]);
        foreach ($rows as $row) {
            if ($row->status !== 'cancelled') {
                $row->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            }
        }
    }

    /** Cancel every active transaction created from a source document (payment, supplier payment…). */
    public static function cancelFor(Model $source, string $reason): void
    {
        Transaction::withoutGlobalScopes()
            ->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
            ->where('status', 'active')->get()
            ->each(fn ($t) => self::cancel($t, $reason));
    }
}
