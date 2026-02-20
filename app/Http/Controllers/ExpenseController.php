<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index()
    {
        return Inertia::render('Expenses/Index', [
            'expenses' => Expense::with('category', 'particular.department')->latest()->get(),
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
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

        Expense::create($validated);

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

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }
}
