<?php

namespace App\Support;

use App\Models\Business\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Writes the "who did what" audit trail. Only actions by a logged-in user are
 * recorded (website visitors' form submissions are not). Logging never breaks
 * the request: failures are only written to the error log.
 */
class ActivityLogger
{
    /** Attributes never stored in the log. */
    private const HIDDEN = ['password', 'remember_token', 'updated_at', 'created_at'];

    public static function record(string $action, ?Model $subject = null, ?string $description = null, ?array $changes = null): void
    {
        $user = Auth::user();
        if (! $user instanceof \App\Models\User) { // staff only; client-portal customers are not logged as users
            return;
        }

        try {
            ActivityLog::create([
                'user_id' => $user->getKey(),
                'branch_id' => self::branchOf($subject),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => mb_substr($description ?? self::describe($action, $subject), 0, 500),
                'changes' => $changes ?: null,
                'ip' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Activity log failed: '.$e->getMessage());
        }
    }

    /** Model event hook: created / updated / deleted. */
    public static function model(string $event, Model $model): void
    {
        $changes = null;
        if ($event === 'updated') {
            $after = array_diff_key($model->getChanges(), array_flip(self::HIDDEN));
            if (! $after) {
                return; // only timestamps changed
            }
            $before = array_intersect_key($model->getOriginal(), $after);
            $changes = ['before' => self::trim($before), 'after' => self::trim($after)];
        } elseif ($event === 'deleted') {
            $changes = ['before' => self::trim(array_diff_key($model->getAttributes(), array_flip(self::HIDDEN)))];
        }

        self::record($event, $model, null, $changes);
    }

    public static function label(?Model $m): string
    {
        if (! $m) {
            return '';
        }
        foreach (['number', 'title', 'name', 'question', 'label', 'email', 'key'] as $attr) {
            $v = $m->getAttribute($attr);
            if (is_scalar($v) && $v !== '') {
                return (string) $v;
            }
        }

        return '#'.$m->getKey();
    }

    private static function describe(string $action, ?Model $subject): string
    {
        if (! $subject) {
            return ucfirst($action);
        }
        $type = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($subject)));

        return ucfirst($action).' '.$type.' "'.self::label($subject).'"';
    }

    /**
     * Only records that belong to a branch (have branch_id: invoices, stock…) are tagged.
     * Company-wide things (users, roles, settings, website content) stay untagged so they
     * show in every branch's log.
     */
    private static function branchOf(?Model $subject): ?int
    {
        $id = $subject?->getAttribute('branch_id');

        return $id ? (int) $id : null;
    }

    /** Keep log rows small: shorten long text (rich HTML etc.). */
    private static function trim(array $values): array
    {
        foreach ($values as $k => $v) {
            if (is_string($v) && mb_strlen($v) > 300) {
                $values[$k] = mb_substr(strip_tags($v), 0, 300).'…';
            }
        }

        return $values;
    }
}
