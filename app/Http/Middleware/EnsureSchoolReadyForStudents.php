<?php

namespace App\Http\Middleware;

use App\Support\Setup\SetupProgress;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks student screens until the school has a current term and at least one class.
 * Applied to student routes from Sprint 3 ("school.ready").
 */
class EnsureSchoolReadyForStudents
{
    public const MESSAGE = 'Set up a current term and at least one class before adding students.';

    public function __construct(private SetupProgress $setupProgress) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->setupProgress->readyForStudents()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 409);
        }

        return redirect()->route('setup')->with('status', self::MESSAGE);
    }
}
