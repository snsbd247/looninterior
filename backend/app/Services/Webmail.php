<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * One-click Webmail: asks cPanel (UAPI Session::create_webmail_session_for_mail_user) for a temporary
 * Roundcube session of the staff member's own mailbox. The browser then POSTs the session to
 * https://host:2096{token}/login. The cPanel API token lives in biz_webmail_token and is never sent to the browser.
 */
class Webmail
{
    public const MASK = '********';

    public static function settings(): array
    {
        $s = Setting::businessArray();

        return [
            'enabled' => ($s['biz_webmail_enabled'] ?? '0') === '1',
            'host' => strtolower(trim((string) ($s['biz_webmail_host'] ?? ''))),
            'user' => trim((string) ($s['biz_webmail_cpanel_user'] ?? '')),
            'token' => trim((string) ($s['biz_webmail_token'] ?? '')),
            'domain' => strtolower(trim((string) ($s['biz_webmail_domain'] ?? ''))),
        ];
    }

    public static function configured(): bool
    {
        $c = self::settings();

        return $c['enabled'] && $c['host'] !== '' && $c['user'] !== '' && $c['token'] !== '';
    }

    /** The user's mailbox: the "Mailbox" field, or the login e-mail when it is on the mail domain. */
    public static function mailboxFor(User $user): ?string
    {
        $box = strtolower(trim((string) $user->mailbox));
        if ($box !== '') {
            return $box;
        }
        $domain = self::settings()['domain'];
        $email = strtolower((string) $user->email);

        return $domain !== '' && str_ends_with($email, '@'.$domain) ? $email : null;
    }

    /** @return array{0: bool, 1: string|array} [true, ['url' => …, 'session' => …]] or [false, error message] */
    public static function session(string $mailbox, ?string $ip): array
    {
        $c = self::settings();
        [$login, $domain] = array_pad(explode('@', $mailbox, 2), 2, '');
        if ($login === '' || $domain === '') {
            return [false, 'The mailbox must be a full e-mail address.'];
        }
        $query = ['login' => $login, 'domain' => $domain, 'locale' => 'en'];
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            $query['remote_address'] = $ip;
        }
        try {
            $res = Http::timeout(20)
                ->withHeaders(['Authorization' => "cpanel {$c['user']}:{$c['token']}"])
                ->acceptJson()
                ->get("https://{$c['host']}:2083/execute/Session/create_webmail_session_for_mail_user", $query);
        } catch (\Throwable $e) {
            return [false, 'Could not reach cPanel: '.$e->getMessage()];
        }
        if ($res->status() === 401 || $res->status() === 403) {
            return [false, 'cPanel refused the API token. Check the cPanel username and token in Business Settings → Webmail.'];
        }
        $j = $res->json();
        $data = $j['data'] ?? ($j['result']['data'] ?? null);
        $status = $j['status'] ?? ($j['result']['status'] ?? 0);
        if (! $res->ok() || ! $status || empty($data['session']) || empty($data['token'])) {
            $errors = $j['errors'] ?? ($j['result']['errors'] ?? null);
            $msg = is_array($errors) && $errors ? implode(' ', $errors) : 'HTTP '.$res->status();

            return [false, "cPanel could not open the mailbox {$mailbox}: {$msg}"];
        }
        $host = ($data['hostname'] ?? '') ?: $c['host'];

        return [true, ['url' => "https://{$host}:2096{$data['token']}/login", 'session' => $data['session']]];
    }
}
