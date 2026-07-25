<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::with('category', 'particular.department')->latest()->get();

        $yearsFromExpenses = $expenses->pluck('date_encoded')
            ->filter()
            ->map(fn($d) => (int) date('Y', strtotime($d)))
            ->unique();

        $budgetYears = \App\Models\AnnualBudget::pluck('year');
        $currentYear = (int) date('Y');

        $availableYears = $yearsFromExpenses->concat($budgetYears)
            ->push($currentYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $defaultYear = $currentYear;

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
            'availableYears' => $availableYears,
            'defaultYear' => $defaultYear,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'category_id' => 'required|exists:budget_categories,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'amount' => 'required|numeric|min:0',
            'paid' => 'nullable|numeric|min:0',
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'required|in:pending,approved,cancelled',
            'notes' => 'nullable|string',
        ]);

        $lastExpense = Expense::latest('id')->first();
        $nextNum = $lastExpense ? intval(substr($lastExpense->ref_no, 3)) + 1 : 1;
        $validated['ref_no'] = 'EXP' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
        $validated['paid'] = $validated['paid'] ?? 0;

        $expense = Expense::create($validated);
        $this->syncBudgetItemExpenditure($expense);

        return redirect()->route('expenses.index')->with('success', 'Expense created.');
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'category_id' => 'required|exists:budget_categories,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'amount' => 'required|numeric|min:0',
            'paid' => 'nullable|numeric|min:0',
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'required|in:pending,approved,cancelled',
            'notes' => 'nullable|string',
        ]);

        $validated['paid'] = $validated['paid'] ?? 0;

        $expense->update($validated);
        $this->syncBudgetItemExpenditure($expense);

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $oldExpense = clone $expense;
        $expense->delete();
        $this->syncBudgetItemExpenditure($oldExpense);

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    protected function syncBudgetItemExpenditure(Expense $expense)
    {
        if (!$expense->date_encoded || !$expense->particular_id) {
            return;
        }

        $year = date('Y', strtotime($expense->date_encoded));
        $budget = \App\Models\AnnualBudget::where('year', $year)->first();

        if ($budget) {
            $budgetItem = \App\Models\BudgetItem::where('budget_id', $budget->id)
                ->where('particular_id', $expense->particular_id)
                ->first();

            if ($budgetItem) {
                $total = Expense::whereYear('date_encoded', $year)
                    ->where('particular_id', $expense->particular_id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');

                $budgetItem->update(['expenditure' => $total]);
            }
        }
    }
}
