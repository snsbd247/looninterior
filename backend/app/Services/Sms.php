<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS through any Bangladeshi gateway that has a simple HTTP API (settings: Business Settings → SMS).
 *
 * - The gateway URL is a template with {to} and {message} (URL-encoded), sent as GET; or POST with a body template.
 * - With no URL set, messages are written to storage/logs/sms.log (safe default for testing).
 * - Event messages are sent only after the database transaction commits, and an SMS failure never breaks the request.
 */
class Sms
{
    public const EVENTS = [
        'payment' => 'Dear {name}, we received Tk {amount} against invoice {invoice}. Receipt: {receipt}. Thank you. - {company}',
        'ticket' => 'Dear {name}, your service request {ticket} is registered. Our team will contact you soon. - {company}',
    ];

    private static ?array $settings = null;

    private static function cfg(string $key, $default = null)
    {
        self::$settings ??= Setting::businessArray();
        $v = self::$settings['biz_sms_'.$key] ?? null;

        return $v === null || $v === '' ? $default : $v;
    }

    public static function enabled(): bool
    {
        return (string) self::cfg('enabled', '0') === '1';
    }

    /** Fire an event SMS (payment / ticket) if SMS and that event are turned on. */
    public static function event(string $event, ?string $phone, array $vars): void
    {
        if (! $phone || ! self::enabled() || (string) self::cfg('on_'.$event, '1') !== '1') {
            return;
        }
        $template = trim((string) self::cfg($event.'_text', '')) ?: self::EVENTS[$event];
        $vars['company'] = $vars['company'] ?? (Setting::businessArray()['biz_company_name'] ?? config('app.name'));
        $text = preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $template);
        DB::afterCommit(fn () => self::send($phone, $text));
    }

    /** Bangladeshi mobile → 8801XXXXXXXXX; anything else is passed through as digits. */
    public static function normalize(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone);
        if (preg_match('/^01\d{9}$/', $d)) {
            return '88'.$d;
        }
        if (preg_match('/^1\d{9}$/', $d)) {
            return '880'.$d;
        }

        return $d;
    }

    /** Send one SMS. Returns [ok, detail]. Never throws. */
    public static function send(string $phone, string $message): array
    {
        $to = self::normalize($phone);
        $url = trim((string) self::cfg('url', ''));
        try {
            if ($url === '') {
                Log::build(['driver' => 'single', 'path' => storage_path('logs/sms.log')])->info("SMS to {$to}: {$message}");

                return [true, 'Written to storage/logs/sms.log (no gateway URL set)'];
            }
            $fill = fn (string $tpl, bool $encode) => strtr($tpl, ['{to}' => $encode ? rawurlencode($to) : $to, '{message}' => $encode ? rawurlencode($message) : $message]);
            $http = Http::timeout(8)->acceptJson();
            if (strtoupper((string) self::cfg('method', 'GET')) === 'POST') {
                $body = (string) self::cfg('body', '');
                $res = str_starts_with(ltrim($body), '{')
                    ? $http->withBody(strtr($body, ['{to}' => substr(json_encode($to), 1, -1), '{message}' => substr(json_encode($message, JSON_UNESCAPED_UNICODE), 1, -1)]), 'application/json')->post($fill($url, true))
                    : $http->asForm()->post($fill($url, true), self::formBody($body, $to, $message));
            } else {
                $res = $http->get($fill($url, true));
            }
            $detail = 'HTTP '.$res->status().': '.mb_substr($res->body(), 0, 200);
            Log::build(['driver' => 'single', 'path' => storage_path('logs/sms.log')])->info("SMS to {$to} → {$detail}");

            return [$res->successful(), $detail];
        } catch (\Throwable $e) {
            Log::error('SMS failed: '.$e->getMessage());

            return [false, $e->getMessage()];
        }
    }

    /** "user=x&pass=y&to={to}&msg={message}" → array (values filled in). */
    private static function formBody(string $body, string $to, string $message): array
    {
        parse_str($body, $pairs);

        return array_map(fn ($v) => strtr((string) $v, ['{to}' => $to, '{message}' => $message]), $pairs);
    }
}
