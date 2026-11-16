<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Support\Notifier;
use App\Support\Uploader;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Setting::websiteArray()]);
    }

    public function update(Request $request)
    {
        $fileKeys = array_keys($request->allFiles());

        $rules = [];
        foreach ($fileKeys as $key) {
            $rules[$key] = 'file|mimes:jpg,jpeg,png,webp,gif,svg,ico|max:8192';
        }
        $request->validate($rules);

        $current = Setting::allAsArray();

        // Text values
        foreach ($request->except($fileKeys) as $key => $value) {
            if (! is_string($key) || ! self::writable($key)) {
                continue;
            }

            if (str_starts_with($key, 'remove_')) {
                $target = substr($key, 7);
                if ($value && self::writable($target) && ! in_array($target, $fileKeys, true)) {
                    Uploader::delete($current[$target] ?? null);
                    Setting::updateOrCreate(['key' => $target], ['value' => null]);
                }
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : $value]
            );
        }

        // Image values
        foreach ($fileKeys as $key) {
            if (! self::writable($key)) {
                continue;
            }
            Uploader::delete($current[$key] ?? null);
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => Uploader::store($request->file($key), 'settings')]
            );
        }

        ActivityLogger::record('updated', null, 'Updated website settings');

        return response()->json(['message' => 'Settings saved', 'data' => Setting::websiteArray()]);
    }

    /** Website settings may not touch business (biz_*) keys: those need settings.business. */
    private static function writable(string $key): bool
    {
        return preg_match('/^[a-z0-9_]{1,100}$/', $key) === 1
            && ! str_starts_with($key, Setting::BUSINESS_PREFIX);
    }

    /** Send a test e-mail to the notification recipients. */
    public function testMail()
    {
        $to = Notifier::recipients();
        if (! $to) {
            return response()->json(['message' => 'Add a Notification E-mail (or a contact e-mail) and save first.'], 422);
        }

        try {
            Mail::send('emails.test', ['site' => Notifier::siteName()], function (Message $m) use ($to) {
                $m->to($to)->subject('Test e-mail | '.Notifier::siteName());
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Sending failed: '.$e->getMessage()], 422);
        }

        $note = config('mail.default') === 'log'
            ? ' (MAIL_MAILER is "log": the e-mail was written to storage/logs/mail.log, not delivered. Set SMTP in .env.)'
            : '';

        return response()->json(['message' => 'Test e-mail sent to '.implode(', ', $to).$note]);
    }
}
