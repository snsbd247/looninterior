<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the branch a business request works in from the `X-Branch-Id` header.
 *  - a branch id: must be one of the user's allowed branches, otherwise 403
 *  - "all":       central view; only users with `branches.all`
 *  - missing:     the user's first allowed branch
 */
class ResolveBranch
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $allowed = $user ? $user->allowedBranches()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        if (! $allowed) {
            return response()->json(['message' => 'Your account is not assigned to any active branch. Please contact the Admin.'], 403);
        }

        $header = trim((string) $request->header('X-Branch-Id', ''));

        if ($header === 'all') {
            if (! $user->hasPermission('branches.all')) {
                return response()->json(['message' => 'You can only view your own branch.'], 403);
            }
            BranchContext::set(null, $allowed);
        } elseif ($header !== '' && ctype_digit($header)) {
            if (! in_array((int) $header, $allowed, true)) {
                return response()->json(['message' => 'You do not have access to this branch.'], 403);
            }
            BranchContext::set((int) $header, $allowed);
        } else {
            BranchContext::set($allowed[0], $allowed);
        }

        return $next($request);
    }
}
