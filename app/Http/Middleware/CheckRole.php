<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    private function normalizeRole(string $role): string
    {
        return match ($role) {
            'budget_monitoring_officer' => 'budget_officer',
            default => $role,
        };
    }

    /**
     * Handle an incoming request.
     * Usage: ->middleware('role:super_admin,budget_officer')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $userRole = $user ? $this->normalizeRole($user->role) : null;
        $allowedRoles = array_map([$this, 'normalizeRole'], $roles);

        if (!$user || !in_array($userRole, $allowedRoles, true)) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                abort(403, 'Unauthorized action.');
            }
            return redirect('/')->with('error', 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
