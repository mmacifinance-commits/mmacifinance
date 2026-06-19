<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('BudgetCategories/Index', [
            'categories' => BudgetCategory::with('particulars')->withCount('particulars', 'budgetItems')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        BudgetCategory::create($validated);

        return redirect()->route('budget-categories.index')->with('success', 'Category created.');
    }

    public function update(Request $request, BudgetCategory $budgetCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $budgetCategory->update($validated);

        return redirect()->route('budget-categories.index')->with('success', 'Category updated.');
    }

    public function destroy(BudgetCategory $budgetCategory)
    {
        $budgetCategory->delete();

        return redirect()->route('budget-categories.index')->with('success', 'Category deleted.');
    }
}
