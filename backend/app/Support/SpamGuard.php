<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Lightweight, key-free spam protection for public forms:
 *  - a hidden "website" honeypot field that real visitors never fill in
 *  - a signed timestamp token (issued with /api/site) that must be at least
 *    MIN_SECONDS old, so scripts posting straight to the API are rejected.
 */
class SpamGuard
{
    public const HONEYPOT = 'website';
    public const TOKEN = 'form_token';
    private const MIN_SECONDS = 3;
    private const MAX_SECONDS = 60 * 60 * 12;

    public static function token(): string
    {
        return Crypt::encryptString((string) time());
    }

    /**
     * Returns true when the request looks like a bot that filled the honeypot
     * (caller should pretend success and drop it). Throws a validation error
     * when the token is missing, forged, too fast or expired.
     */
    public static function isBot(Request $request): bool
    {
        if (filled($request->input(self::HONEYPOT))) {
            return true;
        }

        try {
            $issued = (int) Crypt::decryptString((string) $request->input(self::TOKEN));
        } catch (\Throwable) {
            $issued = 0;
        }

        $age = time() - $issued;
        if ($issued === 0 || $age > self::MAX_SECONDS) {
            throw ValidationException::withMessages([
                'form' => 'Your session has expired. Please refresh the page and try again.',
            ]);
        }
        if ($age < self::MIN_SECONDS) {
            throw ValidationException::withMessages([
                'form' => 'Please take a moment to fill in the form, then try again.',
            ]);
        }

        return false;
    }
}
