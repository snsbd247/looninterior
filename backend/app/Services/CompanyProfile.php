<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Company details printed on business documents, in the document's language.
 * Business settings (biz_*) win; website settings are the fallback, so a new
 * install already prints sensible values.
 */
class CompanyProfile
{
    public static function get(string $lang = 'en'): array
    {
        $s = Setting::allAsArray();
        $pick = function (string $key, ?string $fallback = null) use ($s, $lang) {
            if ($lang === 'bn' && filled($s["biz_{$key}_bn"] ?? null)) {
                return $s["biz_{$key}_bn"];
            }

            return filled($s["biz_{$key}"] ?? null) ? $s["biz_{$key}"] : ($fallback !== null ? ($s[$fallback] ?? null) : null);
        };

        return [
            'name' => $pick('company_name', 'site_name') ?: config('app.name'),
            'address' => $pick('address', 'address'),
            'phone' => $pick('phone', 'phone'),
            'email' => $pick('email', 'email'),
            'website' => $s['biz_website'] ?? rtrim((string) config('app.url'), '/'),
            'bin' => $s['biz_bin'] ?? null,
            'tin' => $s['biz_tin'] ?? null,
            'bank' => $pick('bank_details'),
            'terms' => $pick('terms'),
            'footer' => $pick('footer'),
            'signatory_name' => $pick('signatory_name'),
            'signatory_title' => $pick('signatory_title'),
            'logo' => Pdf::imagePath($s['biz_logo'] ?? null) ?? Pdf::imagePath($s['logo'] ?? null),
            'signature' => Pdf::imagePath($s['biz_signature'] ?? null),
            'seal' => Pdf::imagePath($s['biz_seal'] ?? null),
            'color' => $s['primary_color'] ?? '#b08d57',
            'bn_digits' => ($s['biz_bn_digits'] ?? '1') === '1',
        ];
    }
}
