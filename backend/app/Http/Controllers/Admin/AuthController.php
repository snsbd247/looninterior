<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const RESET_MINUTES = 60;

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }

        return response()->json([
            'token' => $user->createToken('admin-panel')->plainTextToken,
            'user' => $user,
        ]);
    }

    /**
     * E-mail a one-time reset link. Always answers the same way so the
     * endpoint cannot be used to discover which e-mails are admins.
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $answer = response()->json([
            'message' => 'If this e-mail belongs to an admin account, a reset link has been sent to it.',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return $answer;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $url = rtrim((string) config('app.url'), '/').'/admin/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);

        try {
            Mail::send('emails.reset-password', [
                'site' => Notifier::siteName(),
                'name' => $user->name,
                'url' => $url,
                'minutes' => self::RESET_MINUTES,
            ], fn (Message $m) => $m->to($user->email)->subject('Reset your admin password | '.Notifier::siteName()));
        } catch (\Throwable $e) {
            Log::error('Password reset e-mail failed: '.$e->getMessage());
        }

        return $answer;
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        $valid = $row
            && Hash::check($data['token'], $row->token)
            && now()->subMinutes(self::RESET_MINUTES)->lt($row->created_at);
        $user = $valid ? User::where('email', $data['email'])->first() : null;

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => 'This reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        $user->tokens()->delete(); // sign out every other session

        return response()->json(['message' => 'Password changed. You can now sign in with the new password.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'required_with:password|nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
            }
            $user->password = $data['password'];
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }
}
