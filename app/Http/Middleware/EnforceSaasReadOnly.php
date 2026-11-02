<?php

namespace App\Http\Middleware;

use App\Support\Saas\ReadOnlyGuard;
use App\Support\Saas\SaasLock;
use App\Support\Tenancy\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff screens of a school with an unpaid Orange Tree invoice (Pilot preparation step 4): shares
 * the lock state with the layout (the banner), and once locked switches the database guard on,
 * so nothing can be changed until the invoice is paid. Also re-run on Livewire updates
 * (persistent middleware), where the saves happen.
 */
class EnforceSaasReadOnly
{
    public function __construct(private CurrentSchool $currentSchool, private ReadOnlyGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $this->currentSchool->get();
        if ($school !== null) {
            $lock = SaasLock::for($school);
            View::share('saasLock', $lock);

            if ($lock->isLocked()) {
                $this->guard->switchOn();
            }
        }

        return $next($request);
    }
}
