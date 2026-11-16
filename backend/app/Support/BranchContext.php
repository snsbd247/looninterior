<?php

namespace App\Support;

/**
 * The branch the current request works in, resolved by the BranchContext
 * middleware from the X-Branch-Id header and validated against the user.
 *
 *  current() : branch id, or null = "all branches" (central view, admins only)
 *  allowed() : branch ids this user may access
 */
class BranchContext
{
    private static ?int $current = null;
    private static array $allowed = [];
    private static bool $resolved = false;

    public static function set(?int $current, array $allowed): void
    {
        self::$current = $current;
        self::$allowed = array_values(array_map('intval', $allowed));
        self::$resolved = true;
    }

    public static function resolved(): bool
    {
        return self::$resolved;
    }

    public static function current(): ?int
    {
        return self::$current;
    }

    public static function isAll(): bool
    {
        return self::$resolved && self::$current === null;
    }

    public static function allowed(): array
    {
        return self::$allowed;
    }

    /** Branch ids to filter by: the current one, or every allowed one in "all" mode. */
    public static function scopeIds(): array
    {
        return self::$current !== null ? [self::$current] : self::$allowed;
    }

    /** Branch a new record should be saved to (null in "all" mode: the caller must ask). */
    public static function forWrite(): ?int
    {
        return self::$current;
    }

    public static function reset(): void
    {
        self::$current = null;
        self::$allowed = [];
        self::$resolved = false;
    }
}
