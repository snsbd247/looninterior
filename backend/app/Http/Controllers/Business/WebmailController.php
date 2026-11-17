<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\Webmail;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

/** One-click Webmail for staff: each user opens their own cPanel mailbox. */
class WebmailController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'configured' => Webmail::configured(),
            'mailbox' => Webmail::mailboxFor($request->user()),
        ]);
    }

    /** Creates a temporary Webmail session; the browser POSTs it to cPanel's login URL. */
    public function session(Request $request)
    {
        if (! Webmail::configured()) {
            return response()->json(['message' => 'Webmail is not set up yet. An admin can set it up in Business Settings → Webmail.'], 422);
        }
        $mailbox = Webmail::mailboxFor($request->user());
        if (! $mailbox) {
            return response()->json(['message' => 'No mailbox is set for your account. Ask the admin to add your e-mail address in Users → Mailbox.'], 422);
        }
        [$ok, $out] = Webmail::session($mailbox, $request->ip());
        if (! $ok) {
            return response()->json(['message' => $out], 422);
        }
        ActivityLogger::record('webmail_login', null, "Opened Webmail ({$mailbox})");

        return response()->json($out + ['mailbox' => $mailbox]);
    }
}
