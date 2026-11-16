<?php

namespace App\Http\Middleware;

use App\Models\Business\Customer;
use Closure;
use Illuminate\Http\Request;

/** Client portal routes: only a customer token, and only while the portal is enabled for that customer. */
class EnsurePortalCustomer
{
    public function handle(Request $request, Closure $next)
    {
        $c = $request->user();
        if (! $c instanceof Customer) {
            return response()->json(['message' => 'Please sign in to the client portal.'], 401);
        }
        if (! $c->portal_enabled) {
            $c->currentAccessToken()?->delete();

            return response()->json(['message' => 'Portal access is turned off. Please contact us.'], 401);
        }

        return $next($request);
    }
}
