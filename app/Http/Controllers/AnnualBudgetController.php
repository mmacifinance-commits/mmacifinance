<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\AuditTrail;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AnnualBudgetController extends Controller
{
    protected function ensureIncomeExistsForYear(int $year): void
    {
        if (!Income::whereYear('date_encoded', $year)->exists()) {
            throw ValidationException::withMessages([
                'year' => "You must create at least one income record for {$year} before creating appropriation.",
            ]);
        }
    }

    public function index()
    {
        $budgets = AnnualBudget::with(['items.category', 'items.particular.department'])
            ->latest('year')
            ->get();

        // Ensure ref_no is generated for existing annual budgets if null
        foreach ($budgets as $b) {
            if (!$b->ref_no) {
                $b->update(['ref_no' => sprintf('AB-%d-%04d', $b->year, $b->id)]);
            }
            foreach ($b->items as $item) {
                if (!$item->ref_no) {
                    $item->update(['ref_no' => sprintf('MB-%d-%02d-%04d', $b->year, $item->month ?: 1, $item->id)]);
                }
            }
        }

        return Inertia::render('AnnualBudgets/Index', [
            'budgets' => $budgets,
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
            'accountTitles' => BudgetParticular::with('category', 'department')->get(),
            'availableYears' => AnnualBudget::distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    public function show(AnnualBudget $annualBudget)
    {
        if (!$annualBudget->ref_no) {
            $annualBudget->update(['ref_no' => sprintf('AB-%d-%04d', $annualBudget->year, $annualBudget->id)]);
        }

        $budget = $annualBudget->load(['items.category', 'items.particular.department']);
        foreach ($budget->items as $item) {
            if (!$item->ref_no) {
                $item->update(['ref_no' => sprintf('MB-%d-%02d-%04d', $annualBudget->year, $item->month ?: 1, $item->id)]);
            }
        }

        return Inertia::render('AnnualBudgets/Show', [
            'budget' => $budget,
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
            'accountTitles' => BudgetParticular::with('category', 'department')->get(),
            'availableYears' => AnnualBudget::distinct()->orderByDesc('year')->pluck('year'),
            'allBudgets' => AnnualBudget::select('id', 'year', 'ref_no', 'semester')->orderByDesc('year')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'semester' => 'nullable|string|max:20',
        ]);

        $this->ensureIncomeExistsForYear((int) $validated['year']);

        $annualBudget = AnnualBudget::create($validated);
        AuditTrail::log($annualBudget, 'created', auth()->user(), "Created Annual Budget for year {$annualBudget->year}");

        return redirect()->route('annual-budgets.index')->with('success', 'Annual Budget created with reference number ' . $annualBudget->ref_no);
    }

    public function storeItem(Request $request, AnnualBudget $annualBudget)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'month' => 'nullable|integer|min:1|max:12',
            'appropriation' => 'required|numeric|min:0',
            'expenditure' => 'nullable|numeric|min:0',
        ]);

        $this->ensureIncomeExistsForYear((int) $annualBudget->year);

        $validated['month'] = $validated['month'] ?: 1;

        $item = $annualBudget->items()->create($validated);
        AuditTrail::log($item, 'created', auth()->user(), "Added Monthly Budget Allocation item {$item->ref_no}");

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Monthly Budget Allocation added.');
    }

    public function updateItem(Request $request, AnnualBudget $annualBudget, BudgetItem $item)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'month' => 'nullable|integer|min:1|max:12',
            'appropriation' => 'required|numeric|min:0',
            'expenditure' => 'nullable|numeric|min:0',
        ]);

        $this->ensureIncomeExistsForYear((int) $annualBudget->year);

        $validated['month'] = $validated['month'] ?: $item->month ?: 1;

        $item->update($validated);
        AuditTrail::log($item, 'modified', auth()->user(), "Updated Monthly Budget Allocation item {$item->ref_no}");

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Monthly Budget Allocation updated.');
    }

    public function destroyItem(AnnualBudget $annualBudget, BudgetItem $item)
    {
        AuditTrail::log($item, 'deleted', auth()->user(), "Deleted Monthly Budget Allocation item {$item->ref_no}");
        $item->delete();

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Monthly Budget Allocation deleted.');
    }

    public function destroy(AnnualBudget $annualBudget)
    {
        AuditTrail::log($annualBudget, 'deleted', auth()->user(), "Deleted Annual Budget {$annualBudget->ref_no}");
        $annualBudget->delete();

        return redirect()->route('annual-budgets.index')->with('success', 'Annual Budget deleted.');
    }
}
