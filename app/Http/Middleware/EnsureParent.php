<?php

namespace App\Http\Middleware;

use App\Actions\ParentAuth\IssueParentToken;
use App\Models\Guardian;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parent API routes: the caller must use a parent-app token (not a session) and be linked to a
 * guardian of this school. The guardian is put on the request ("guardian") for the controllers.
 */
class EnsureParent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $guardian = $user instanceof User && $request->bearerToken() !== null
            && $user->tokenCan(IssueParentToken::ABILITY)
            ? Guardian::where('user_id', $user->id)->first()
            : null;

        if ($guardian === null) {
            return response()->json(['message' => 'This is not a parent app account.'], 403);
        }

        $request->attributes->set('guardian', $guardian);

        return $next($request);
    }
}
