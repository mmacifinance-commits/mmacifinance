<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\AuditTrail;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::with([
            'category',
            'particular.department',
            'disbursements',
            'auditTrails',
        ])->latest()->get();

        $yearsFromExpenses = $expenses->pluck('date_encoded')
            ->filter()
            ->map(fn($d) => (int) date('Y', strtotime($d)))
            ->unique()
            ->sortDesc()
            ->values();

        $budgetYears = \App\Models\AnnualBudget::pluck('year');
        $currentYear = (int) date('Y');

        $availableYears = $yearsFromExpenses->concat($budgetYears)
            ->push($currentYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $defaultYear = $yearsFromExpenses->first() ?? $budgetYears->sortDesc()->values()->first() ?? $currentYear;

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
            'budgetYears' => AnnualBudget::pluck('year')->values()->toArray(),
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
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $year = (int) date('Y', strtotime($validated['date_encoded']));
        if (!AnnualBudget::where('year', $year)->exists()) {
            throw ValidationException::withMessages([
                'date_encoded' => "No annual budget exists for FY {$year}. Please create the annual budget first.",
            ]);
        }

        $validated['status'] = strtolower(trim((string) ($validated['status'] ?? 'pending')));
        if (!in_array($validated['status'], ['pending', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'New expenditures must start as Pending or Cancelled. Use the workflow actions to submit and approve them.',
            ]);
        }

        $lastExpense = Expense::latest('id')->first();
        $nextNum = $lastExpense ? intval(substr($lastExpense->ref_no, 3)) + 1 : 1;
        $validated['ref_no'] = 'EXP' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);

        $expense = Expense::create($validated);

        $warning = $this->budgetOverrunWarning($expense);

        return redirect()->route('expenses.index')->with([
            'success' => 'Expense created.',
            'warning' => $warning,
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'category_id' => 'required|exists:budget_categories,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $year = (int) date('Y', strtotime($validated['date_encoded']));
        if (!AnnualBudget::where('year', $year)->exists()) {
            throw ValidationException::withMessages([
                'date_encoded' => "No annual budget exists for FY {$year}. Please create the annual budget first.",
            ]);
        }

        $validated['status'] = strtolower(trim((string) ($validated['status'] ?? $expense->status ?? 'pending')));
        $protectedWorkflowStatuses = ['for_approval', 'approved', 'rejected', 'returned_for_revision', 'posted'];
        $directStatuses = ['pending', 'cancelled'];

        if (in_array($expense->status, $protectedWorkflowStatuses, true)) {
            if ($validated['status'] !== $expense->status) {
                throw ValidationException::withMessages([
                    'status' => 'This expense uses workflow-controlled status. Use the approval actions to change it.',
                ]);
            }
            $validated['status'] = $expense->status;
        } elseif (!in_array($validated['status'], $directStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => 'Expenditures can only be edited as Pending or Cancelled. Submit/approve/post them through the workflow actions.',
            ]);
        }

        $expense->update($validated);

        $warning = $this->budgetOverrunWarning($expense);

        return redirect()->route('expenses.index')->with([
            'success' => 'Expense updated.',
            'warning' => $warning,
        ]);
    }

    public function submitForApproval(Request $request, Expense $expense)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        if (!auth()->user()?->canSubmitExpenses()) {
            abort(403, 'You are not allowed to submit expenses for approval.');
        }

        if (!in_array($expense->status, ['pending', 'returned_for_revision'], true)) {
            return redirect()->back()->with('error', 'Only pending or returned expenses can be submitted for approval.');
        }

        $expense->update([
            'status' => 'for_approval',
            'date_approved' => $expense->date_approved ?: null,
        ]);

        AuditTrail::log(
            $expense,
            'submitted',
            auth()->user(),
            $request->remarks ?: 'Submitted to Head of Finance for approval.',
            ['status' => 'for_approval']
        );

        return redirect()->route('expenses.index')->with('success', 'Expense submitted for approval.');
    }

    public function approve(Request $request, Expense $expense)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only the Head of Finance can approve expenditures.');
        }

        if ($expense->status !== 'for_approval') {
            return redirect()->back()->with('error', 'Expense must be in For Approval status before it can be approved.');
        }

        $expense->update([
            'status' => 'approved',
            'date_approved' => now(),
        ]);

        AuditTrail::log(
            $expense,
            'approved',
            auth()->user(),
            $request->remarks ?: 'Approved by Head of Finance.',
            ['status' => 'approved']
        );

        return redirect()->route('expenses.index')->with('success', 'Expense approved.');
    }

    public function returnForRevision(Request $request, Expense $expense)
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only the Head of Finance can return expenditures for revision.');
        }

        if ($expense->status !== 'for_approval') {
            return redirect()->back()->with('error', 'Expense must be in For Approval status before it can be returned.');
        }

        $expense->update([
            'status' => 'returned_for_revision',
        ]);

        AuditTrail::log(
            $expense,
            'returned_for_revision',
            auth()->user(),
            $request->remarks ?: 'Returned for revision by Head of Finance.',
            ['status' => 'returned_for_revision']
        );

        return redirect()->route('expenses.index')->with('success', 'Expense returned for revision.');
    }

    public function reject(Request $request, Expense $expense)
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only the Head of Finance can reject expenditures.');
        }

        if ($expense->status !== 'for_approval') {
            return redirect()->back()->with('error', 'Expense must be in For Approval status before it can be rejected.'); 
        }

        $expense->update([
            'status' => 'rejected',
        ]);

        AuditTrail::log(
            $expense,
            'rejected',
            auth()->user(),
            $request->remarks ?: 'Rejected by Head of Finance.',
            ['status' => 'rejected']
        );

        return redirect()->route('expenses.index')->with('success', 'Expense rejected.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    protected function budgetOverrunWarning(Expense $expense): ?string
    {
        if (!$expense->date_encoded || !$expense->particular_id) {
            return null;
        }

        $year = (int) date('Y', strtotime($expense->date_encoded));
        $budget = AnnualBudget::where('year', $year)->first();
        if (!$budget) {
            return null;
        }

        $budgetItem = BudgetItem::where('budget_id', $budget->id)
            ->where('particular_id', $expense->particular_id)
            ->first();

        if (!$budgetItem) {
            return null;
        }

        $totalPaid = Disbursement::where('status', 'posted')
            ->whereHas('expense', function ($query) use ($year, $expense) {
                $query->whereYear('date_encoded', $year)
                    ->where('particular_id', $expense->particular_id);
            })
            ->sum('amount');

        $budgetAmount = (float) $budgetItem->appropriation;
        if ($totalPaid > $budgetAmount) {
            $over = $totalPaid - $budgetAmount;
            return 'This expense is over the budget by ' . number_format($over, 2) . '. It will still be saved so you can monitor the overrun.';
        }

        return null;
    }
}
