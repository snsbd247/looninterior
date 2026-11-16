<?php

namespace App\Support;

use App\Models\Inquiry;
use App\Models\JobApplication;
use App\Models\Setting;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends admin e-mail notifications. A mail failure is logged and never
 * breaks the visitor's form submission.
 */
class Notifier
{
    /** Notification recipients: Settings → Notifications, falling back to the contact e-mail. */
    public static function recipients(): array
    {
        $s = Setting::allAsArray();
        $raw = trim((string) ($s['notify_email'] ?? '')) ?: (string) ($s['email'] ?? '');

        return array_values(array_filter(
            array_map('trim', preg_split('/[,;\s]+/', $raw) ?: []),
            fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)
        ));
    }

    public static function siteName(): string
    {
        return (string) (Setting::where('key', 'site_name')->value('value') ?: config('app.name'));
    }

    public static function adminUrl(string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/').'/admin'.$path;
    }

    public static function inquiry(Inquiry $inquiry): void
    {
        $to = self::recipients();
        if (! $to) {
            return;
        }

        $site = self::siteName();
        $label = $inquiry->type === 'contact' ? 'contact message' : 'quote request';

        self::safely(function () use ($to, $inquiry, $site, $label) {
            Mail::send('emails.inquiry', [
                'site' => $site,
                'label' => $label,
                'i' => $inquiry,
                'url' => self::adminUrl('/r/inquiries/'.$inquiry->id),
            ], function (Message $m) use ($to, $inquiry, $site, $label) {
                $m->to($to)->subject("New {$label} from {$inquiry->name} | {$site}");
                if ($inquiry->email) {
                    $m->replyTo($inquiry->email, $inquiry->name);
                }
            });
        });
    }

    public static function application(JobApplication $app, string $jobTitle): void
    {
        $to = self::recipients();
        if (! $to) {
            return;
        }

        $site = self::siteName();

        self::safely(function () use ($to, $app, $jobTitle, $site) {
            Mail::send('emails.application', [
                'site' => $site,
                'job' => $jobTitle,
                'a' => $app,
                'url' => self::adminUrl('/r/job_applications/'.$app->id),
            ], function (Message $m) use ($to, $app, $jobTitle, $site) {
                $m->to($to)->subject("New application: {$jobTitle} ({$app->name}) | {$site}")
                    ->replyTo($app->email, $app->name);
                $cv = $app->cv_path ? storage_path('app/'.$app->cv_path) : null;
                if ($cv && is_file($cv)) {
                    $m->attach($cv, ['as' => 'CV-'.preg_replace('/[^A-Za-z0-9]+/', '-', $app->name).'.'.pathinfo($cv, PATHINFO_EXTENSION)]);
                }
            });
        });
    }

    private static function safely(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error('Notification e-mail failed: '.$e->getMessage());
        }
    }
}
