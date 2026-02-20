<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnualBudgetController extends Controller
{
    public function index()
    {
        return Inertia::render('AnnualBudgets/Index', [
            'budgets' => AnnualBudget::with('items.category', 'items.particular.department')
                ->latest('year')
                ->get(),
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
        ]);
    }

    public function show(AnnualBudget $annualBudget)
    {
        return Inertia::render('AnnualBudgets/Show', [
            'budget' => $annualBudget->load('items.category', 'items.particular.department'),
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100|unique:annual_budgets,year',
        ]);

        AnnualBudget::create($validated);

        return redirect()->route('annual-budgets.index')->with('success', 'Budget year created.');
    }

    public function storeItem(Request $request, AnnualBudget $annualBudget)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'appropriation' => 'required|numeric|min:0',
            'expenditure' => 'nullable|numeric|min:0',
        ]);

        $annualBudget->items()->create($validated);

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Budget item added.');
    }

    public function updateItem(Request $request, AnnualBudget $annualBudget, BudgetItem $item)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'appropriation' => 'required|numeric|min:0',
            'expenditure' => 'nullable|numeric|min:0',
        ]);

        $item->update($validated);

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Budget item updated.');
    }

    public function destroyItem(AnnualBudget $annualBudget, BudgetItem $item)
    {
        $item->delete();

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Budget item deleted.');
    }

    public function destroy(AnnualBudget $annualBudget)
    {
        $annualBudget->delete();

        return redirect()->route('annual-budgets.index')->with('success', 'Budget year deleted.');
    }
}
