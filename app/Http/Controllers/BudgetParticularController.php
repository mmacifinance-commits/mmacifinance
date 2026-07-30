<?php

namespace App\Http\Controllers;

use App\Models\BudgetParticular;
use App\Models\BudgetCategory;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetParticularController extends Controller
{
    public function index()
    {
        $particulars = BudgetParticular::with('category', 'department')->latest()->get();

        return Inertia::render('BudgetParticulars/Index', [
            'particulars' => $particulars,
            'accountTitles' => $particulars,
            'categories' => BudgetCategory::all(),
            'departments' => Department::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'account_code' => 'required|string|max:20',
            'account_name' => 'required|string|max:255',
            'particular' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        BudgetParticular::create($validated);

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title created successfully.');
    }

    public function update(Request $request, BudgetParticular $budgetParticular)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'account_code' => 'required|string|max:20',
            'account_name' => 'required|string|max:255',
            'particular' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $budgetParticular->update($validated);

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title updated successfully.');
    }

    public function destroy(BudgetParticular $budgetParticular)
    {
        $budgetParticular->delete();

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title deleted successfully.');
    }
}
