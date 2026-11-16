<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Route guard: `->middleware('perm:billing.manage')`.
 * Several permissions separated by "|" mean "any of them" (perm:crm.view|crm.manage).
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = $request->user();
        foreach (explode('|', $permissions) as $perm) {
            if ($user && $user->hasPermission(trim($perm))) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'You do not have permission to do this.'], 403);
    }
}
