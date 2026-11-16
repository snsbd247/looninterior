<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Settings that only the admin panel may see (never sent to the public site). */
    public const PRIVATE_KEYS = ['notify_email'];

    public static function allAsArray(): array
    {
        return static::query()->pluck('value', 'key')->toArray();
    }

    public static function publicArray(): array
    {
        return array_diff_key(static::allAsArray(), array_flip(self::PRIVATE_KEYS));
    }
}
