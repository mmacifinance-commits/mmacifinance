<?php

namespace App\Http\Controllers;

use App\Models\Disbursement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DisbursementController extends Controller
{
    public function index(Request $request)
    {
        $disbursements = Disbursement::with('expense')->latest()->get();

        $yearsFromDsb = $disbursements->pluck('date_encoded')
            ->filter()
            ->map(fn($d) => (int) date('Y', strtotime($d)))
            ->unique();

        $budgetYears = \App\Models\AnnualBudget::pluck('year');
        $currentYear = (int) date('Y');

        $availableYears = $yearsFromDsb->concat($budgetYears)
            ->push($currentYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $defaultYear = $currentYear;

        return Inertia::render('Disbursements/Index', [
            'disbursements' => $disbursements,
            'expenses' => \App\Models\Expense::select('id', 'ref_no', 'description', 'amount', 'paid', 'date_encoded', 'created_at')->get(),
            'availableYears' => $availableYears,
            'defaultYear' => $defaultYear,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_id' => 'required|exists:expenses,id',
            'description' => 'required|string|max:255',
            'source' => 'required|string|max:255',
            'pay_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:check,cash,bank_transfer',
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'required|in:pending,approved,posted,cancelled',
            'notes' => 'nullable|string',
        ], [
            'expense_id.required' => 'Please select a linked expense.',
        ]);

        $lastDsb = Disbursement::latest('id')->first();
        $nextNum = $lastDsb ? intval(substr($lastDsb->disbursement_no, 3)) + 1 : 1;
        $validated['disbursement_no'] = 'DSB' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);

        $dsb = Disbursement::create($validated);
        if ($dsb->expense_id) {
            $this->syncExpensePaidAmount($dsb->expense_id);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement created.');
    }

    public function update(Request $request, Disbursement $disbursement)
    {
        $oldExpenseId = $disbursement->expense_id;

        $validated = $request->validate([
            'expense_id' => 'required|exists:expenses,id',
            'description' => 'required|string|max:255',
            'source' => 'required|string|max:255',
            'pay_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:check,cash,bank_transfer',
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'required|in:pending,approved,posted,cancelled',
            'notes' => 'nullable|string',
        ], [
            'expense_id.required' => 'Please select a linked expense.',
        ]);

        $disbursement->update($validated);

        if ($oldExpenseId) {
            $this->syncExpensePaidAmount($oldExpenseId);
        }
        if ($disbursement->expense_id && $disbursement->expense_id !== $oldExpenseId) {
            $this->syncExpensePaidAmount($disbursement->expense_id);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement updated.');
    }

    public function destroy(Disbursement $disbursement)
    {
        $expenseId = $disbursement->expense_id;
        $disbursement->delete();

        if ($expenseId) {
            $this->syncExpensePaidAmount($expenseId);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement deleted.');
    }

    protected function syncExpensePaidAmount(?int $expenseId)
    {
        if (!$expenseId) return;

        $expense = \App\Models\Expense::find($expenseId);
        if ($expense) {
            $totalPaid = Disbursement::where('expense_id', $expenseId)
                ->whereIn('status', ['approved', 'posted'])
                ->sum('amount');

            $expense->update(['paid' => $totalPaid]);
        }
    }
}
