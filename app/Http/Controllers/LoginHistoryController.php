<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginHistoryController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('LoginHistory/Index', [
            'histories' => LoginHistory::with('user:id,name')
                ->latest('logged_in_at')->paginate(30)->withQueryString(),
        ]);
    }
}
