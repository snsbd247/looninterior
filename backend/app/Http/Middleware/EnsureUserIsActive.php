<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** A deactivated user is signed out immediately, even with a still-valid token. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->is_active === false) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your account has been deactivated.'], 401);
        }

        return $next($request);
    }
}
