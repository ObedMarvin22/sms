<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects a logged-in user (session or API token) who is not a member of the current school.
 *
 * SchoolUserProvider already scopes session logins; this also covers Sanctum tokens,
 * which load their user directly from the token rather than through the provider.
 */
class EnsureUserBelongsToSchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A token outlives a deactivation (Sanctum loads its user directly), so check that too.
        if (! $user instanceof User || ($user->school_id === $this->currentSchool->id() && $user->deactivated_at === null)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return $user->deactivated_at !== null
                ? response()->json(['message' => 'This account has been switched off.'], 401)
                : response()->json(['message' => 'This account does not belong to this school.'], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
