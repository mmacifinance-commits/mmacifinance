<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginHistoryController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('LoginHistory/Index', [
            'histories' => AuditTrail::with(['user:id,name', 'auditable'])
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }
}
