<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\Disbursement;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index', [
            'budgets' => AnnualBudget::with('items.category', 'items.particular.department')->get(),
            'categories' => BudgetCategory::all(),
            'expenses' => Expense::with('category', 'particular')->get(),
            'disbursements' => Disbursement::all(),
        ]);
    }
}
