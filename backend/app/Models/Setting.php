<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Settings that only the admin panel may see (never sent to the public site). */
    public const PRIVATE_KEYS = ['notify_email'];

    /** Business settings (company, bank, signature…): only via the Business Settings API. */
    public const BUSINESS_PREFIX = 'biz_';

    public static function allAsArray(): array
    {
        return static::query()->pluck('value', 'key')->toArray();
    }

    /** Website settings for the admin "Site Settings" page (business keys excluded). */
    public static function websiteArray(): array
    {
        return array_filter(
            static::allAsArray(),
            fn ($key) => ! str_starts_with($key, self::BUSINESS_PREFIX),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function businessArray(): array
    {
        return array_filter(
            static::allAsArray(),
            fn ($key) => str_starts_with($key, self::BUSINESS_PREFIX),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function publicArray(): array
    {
        return array_diff_key(static::websiteArray(), array_flip(self::PRIVATE_KEYS));
    }
}
