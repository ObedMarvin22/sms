<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response in production and staging (SPEC section 14: HTTPS + HSTS).
 * Not locally, where the app runs on plain http://*.localhost.
 *
 * HSTS starts at 6 months without "preload": preload lists are hard to leave, so that waits until
 * every subdomain has run on HTTPS for a while.
 */
class SecurityHeaders
{
    public const HSTS = 'max-age=15552000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment(['production', 'staging'])) {
            return $response;
        }

        $headers = $response->headers;
        $headers->set('Strict-Transport-Security', self::HSTS);
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
