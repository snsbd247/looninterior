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
        return self::mailboxOf($user->mailbox, $user->email);
    }

    public static function mailboxOf(?string $mailbox, ?string $email): ?string
    {
        $box = strtolower(trim((string) $mailbox));
        if ($box !== '') {
            return $box;
        }
        $email = strtolower(trim((string) $email));

        return self::onDomain($email) ? $email : null;
    }

    public static function onDomain(string $email): bool
    {
        $domain = self::settings()['domain'];

        return $domain !== '' && str_ends_with(strtolower($email), '@'.$domain);
    }

    /** Calls a cPanel UAPI function. @return array{0: bool, 1: mixed} [true, data] or [false, error message] */
    public static function cpanel(string $fn, array $params = [], bool $post = false): array
    {
        $c = self::settings();
        try {
            $http = Http::timeout(30)->acceptJson()->withHeaders(['Authorization' => "cpanel {$c['user']}:{$c['token']}"]);
            $url = "https://{$c['host']}:2083/execute/{$fn}";
            $res = $post ? $http->asForm()->post($url, $params) : $http->get($url, $params);
        } catch (\Throwable $e) {
            return [false, 'Could not reach cPanel: '.$e->getMessage()];
        }
        if ($res->status() === 401 || $res->status() === 403) {
            return [false, 'cPanel refused the API token. Check Business Settings → Webmail.'];
        }
        $j = $res->json();
        if (! $res->ok() || empty($j['status'])) {
            $errors = $j['errors'] ?? null;

            return [false, is_array($errors) && $errors ? implode(' ', $errors) : 'cPanel error (HTTP '.$res->status().')'];
        }

        return [true, $j['data'] ?? null];
    }

    /**
     * Mailbox status for the Users form.
     * @return array{status: string, message: string} status: exists | available | other_domain | not_configured | error
     */
    public static function check(string $email): array
    {
        $email = strtolower(trim($email));
        if (! self::configured()) {
            return ['status' => 'not_configured', 'message' => 'Webmail is not set up (Business Settings → Webmail).'];
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! self::onDomain($email)) {
            return ['status' => 'other_domain', 'message' => 'Not a company e-mail (@'.self::settings()['domain'].'): no mailbox is created.'];
        }
        [$ok, $data] = self::cpanel('Email/list_pops');
        if (! $ok) {
            return ['status' => 'error', 'message' => $data];
        }
        foreach ((array) $data as $row) {
            if (strtolower((string) ($row['email'] ?? '')) === $email) {
                return ['status' => 'exists', 'message' => 'Mailbox exists. Webmail will open it.'];
            }
        }

        return ['status' => 'available', 'message' => 'No mailbox with this address yet. It can be created now.'];
    }

    /** Creates a mailbox on the company domain. @return array{0: bool, 1: string} */
    public static function create(string $email, ?string $password = null): array
    {
        [$local, $domain] = explode('@', strtolower(trim($email)), 2);
        $quota = (int) (Setting::businessArray()['biz_webmail_quota'] ?? 2048);
        $password = $password ?: rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Kq'), '=').'#7a';
        [$ok, $out] = self::cpanel('Email/add_pop', ['email' => $local, 'domain' => $domain, 'password' => $password, 'quota' => $quota], true);

        return [$ok, $ok ? 'Mailbox created' : $out];
    }

    /** Blocks or allows signing in to a mailbox (mail keeps arriving). */
    public static function setLogin(string $email, bool $allowed): array
    {
        return self::cpanel($allowed ? 'Email/unsuspend_login' : 'Email/suspend_login', ['email' => strtolower($email)], true);
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
