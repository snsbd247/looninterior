<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Delete" for business records without losing history.
 *
 * A record is really deleted only when nothing else points to it. The database foreign keys are read from
 * information_schema, so a new table that references this one is covered automatically.
 * - ON DELETE CASCADE children are the record's own parts (invoice items, lead activities…) and do not block,
 *   unless listed in $block.
 * - Every other reference (RESTRICT or SET NULL) blocks: deleting would break or blank out another document.
 *
 * Financial and stock documents are never deleted (they are cancelled instead); do not call this for them.
 */
class SafeDelete
{
    private static array $refs = [];

    /** Foreign keys pointing to $table: [[table, column, rule], …] */
    public static function references(string $table): array
    {
        return self::$refs[$table] ??= array_map(fn ($r) => [$r->t, $r->c, strtoupper($r->rule)], DB::select(
            'SELECT k.TABLE_NAME t, k.COLUMN_NAME c, r.DELETE_RULE `rule`
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                 ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              WHERE k.REFERENCED_TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = ? AND k.REFERENCED_COLUMN_NAME = ?',
            [$table, 'id']
        ));
    }

    /**
     * Where the record is used: ['invoices' => 3, …]. Counts ignore branch scopes (all branches count).
     *
     * @param  string[]  $block   cascade tables that must block too (e.g. history worth keeping)
     * @param  string[]  $ignore  tables to skip (handled by the caller inside the same transaction)
     */
    public static function usage(Model $m, array $block = [], array $ignore = []): array
    {
        $out = [];
        foreach (self::references($m->getTable()) as [$table, $column, $rule]) {
            if (in_array($table, $ignore, true) || ($rule === 'CASCADE' && ! in_array($table, $block, true))) {
                continue;
            }
            $q = DB::table($table)->where($column, $m->getKey());
            if ($table === $m->getTable()) {
                $q->where('id', '!=', $m->getKey());
            }
            if ($n = $q->count()) {
                $out[$table] = ($out[$table] ?? 0) + $n;
            }
        }

        return $out;
    }

    /**
     * Delete, or explain why not. $hint is what to do instead ("Mark it inactive instead.").
     * $before runs inside the transaction (e.g. remove empty auto-created children); $after runs after commit (files).
     */
    public static function delete(Model $m, string $what, string $hint = 'Mark it inactive instead.', array $block = [], array $ignore = [], ?callable $before = null, ?callable $after = null): void
    {
        DB::transaction(function () use ($m, $what, $hint, $block, $ignore, $before) {
            $m->newQuery()->withoutGlobalScopes()->lockForUpdate()->find($m->getKey());
            if ($before) {
                $before($m);
            }
            if ($used = self::usage($m, $block, $ignore)) {
                throw ValidationException::withMessages(['delete' => self::message($what, $used, $hint)]);
            }
            $m->delete();
            if (! in_array(\App\Models\Concerns\LogsActivity::class, class_uses_recursive($m), true)) {
                ActivityLogger::record('deleted', $m, "Deleted {$what}"); // models with LogsActivity log it themselves
            }
        });
        if ($after) {
            $after($m);
        }
    }

    public static function message(string $what, array $used, string $hint): string
    {
        $parts = [];
        foreach ($used as $table => $n) {
            $name = ['branch_user' => 'users (branch access)', 'work_projects' => 'projects'][$table] ?? str_replace('_', ' ', $table);
            if ($n === 1) {
                $name = preg_replace(['/ies$/', '/ses$/', '/s$/'], ['y', 's', ''], $name);
            }
            $parts[] = "{$n} {$name}";
        }

        return "This {$what} cannot be deleted because it is used in ".implode(', ', $parts).". {$hint}";
    }
}
