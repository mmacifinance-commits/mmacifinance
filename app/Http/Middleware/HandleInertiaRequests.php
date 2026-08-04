<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->role_label,
                ] : null,
            ],
            'permissions' => $user ? [
                'canManageBudget' => $user->canManageBudget(),
                'canManageExpenses' => $user->canManageExpenses(),
                'canSubmitExpenses' => $user->canSubmitExpenses(),
                'canManageDisbursements' => $user->canManageDisbursements(),
                'canManageIncome' => $user->canManageIncome(),
                'isSuperAdmin' => $user->isSuperAdmin(),
            ] : [],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'message' => fn() => $request->session()->get('message'),
                'warning' => fn() => $request->session()->get('warning'),
            ],
        ];
    }
}
