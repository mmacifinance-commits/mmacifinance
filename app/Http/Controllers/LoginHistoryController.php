<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginHistoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditTrail::with(['user:id,name', 'auditable']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_role', 'like', "%{$search}%")
                    ->orWhere('metadata', 'like', "%{$search}%");
            });
        }

        $query->when(!empty($filters['action']), fn ($q) => $q->where('action', $filters['action']))
            ->when(!empty($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(!empty($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']));

        return Inertia::render('LoginHistory/Index', [
            'histories' => $query->latest()->paginate(30)->withQueryString(),
            'filters' => $filters,
            'actions' => AuditTrail::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
