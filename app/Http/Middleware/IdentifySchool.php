<?php

namespace App\Http\Middleware;

use App\Enums\SchoolStatus;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the school from the subdomain of a "{school}.base_domain" route.
 *
 * Unknown subdomain: 404. Suspended school: suspended page (403).
 * Also registered as Livewire persistent middleware, so component updates resolve it too.
 */
class IdentifySchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $subdomain = $route?->parameter('school');

        abort_unless(is_string($subdomain), 404);

        // Controllers and components never receive {school}; they use CurrentSchool.
        $route->forgetParameter('school');

        $school = School::where('subdomain', strtolower($subdomain))->first();

        abort_if($school === null, 404);

        if ($school->status === SchoolStatus::Suspended) {
            return $this->suspended($request, $school);
        }

        $this->currentSchool->set($school);

        // route('dashboard') etc. stay on this school's subdomain without passing it.
        URL::defaults(['school' => $school->subdomain]);
        View::share('currentSchool', $school);

        return $next($request);
    }

    private function suspended(Request $request, School $school): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'This school account is suspended.'], 403);
        }

        return response()->view('tenancy.suspended', ['school' => $school], 403);
    }
}
