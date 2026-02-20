<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('role:super_admin,budget_officer')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                abort(403, 'Unauthorized action.');
            }
            return redirect('/')->with('error', 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
