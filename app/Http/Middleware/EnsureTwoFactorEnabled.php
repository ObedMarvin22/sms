<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff whose role handles money or approvals must set up two-factor before using the app.
 *
 * Applied to the signed-in staff screens only. The security settings page, Fortify's
 * two-factor setup routes and logout live in other route groups, so they stay reachable.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasConfirmedTwoFactor() || ! $user->requiresTwoFactor()) {
            return $next($request);
        }

        $message = __('Your role requires two-factor authentication. Please set it up to continue.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('security.edit')->with('status', $message);
    }
}
