<?php

namespace App\Services;

use App\Models\Business\Branch;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, never-duplicated document numbers per branch, type and year,
 * e.g. "DHK-INV-2026-0001".
 *
 * next() increments the sequence row in one atomic statement (exclusive row lock),
 * so two people saving at the same moment can never get the same number. Call it inside the same
 * DB::transaction that saves the document; if that transaction rolls back,
 * the number is released too.
 *
 * Types: QT (quotation), INV (invoice), RCV (receipt), PO, GRN, TKT, …
 */
class DocumentNumber
{
    public const DEFAULT_FORMAT = '{BRANCH}-{TYPE}-{YYYY}-{SEQ}';
    public const DEFAULT_PADDING = 4;

    public static function next(string $type, int $branchId, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $type = strtoupper($type);

        // One atomic statement: inserts the first number, or increments the existing one under
        // an exclusive row lock. (A separate insert + SELECT … FOR UPDATE deadlocks under load.)
        // LAST_INSERT_ID(expr) hands the new value back to this connection only.
        $number = DB::transaction(function () use ($type, $branchId, $year) {
            $now = now()->toDateTimeString();
            $affected = DB::affectingStatement(
                'INSERT INTO document_sequences (branch_id, type, year, last_number, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1), updated_at = VALUES(updated_at)',
                [$branchId, $type, $year, $now, $now]
            );

            // 1 = new row inserted (first number), 2 = existing row incremented
            return $affected === 1 ? 1 : (int) DB::selectOne('SELECT LAST_INSERT_ID() AS n')->n;
        }, 5);

        return self::format($type, $branchId, $year, $number);
    }

    /** What the next number will look like, without using it up. */
    public static function preview(string $type, int $branchId, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $last = (int) DB::table('document_sequences')
            ->where(['branch_id' => $branchId, 'type' => strtoupper($type), 'year' => $year])
            ->value('last_number');

        return self::format(strtoupper($type), $branchId, $year, $last + 1);
    }

    public static function format(string $type, int $branchId, int $year, int $number): string
    {
        $settings = Setting::businessArray();
        $format = trim((string) ($settings['biz_number_format'] ?? '')) ?: self::DEFAULT_FORMAT;
        $padding = max(1, min(10, (int) ($settings['biz_number_padding'] ?? self::DEFAULT_PADDING)));
        $code = Branch::whereKey($branchId)->value('code') ?: 'BR'.$branchId;

        return strtr($format, [
            '{BRANCH}' => $code,
            '{TYPE}' => $type,
            '{YYYY}' => (string) $year,
            '{YY}' => substr((string) $year, -2),
            '{SEQ}' => str_pad((string) $number, $padding, '0', STR_PAD_LEFT),
        ]);
    }
}
