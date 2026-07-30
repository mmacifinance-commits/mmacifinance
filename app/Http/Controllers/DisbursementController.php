<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Disbursement;
use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DisbursementController extends Controller
{
    public function index(Request $request)
    {
        $disbursements = Disbursement::with([
            'expense.category',
            'expense.particular',
            'preparedBy',
            'releasedBy',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'postedBy',
            'auditTrails',
        ])->latest()->get();

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
            'expenses' => Expense::select('id', 'ref_no', 'description', 'amount', 'paid', 'date_encoded', 'created_at')->get(),
            'availableYears' => $availableYears,
            'defaultYear' => $defaultYear,
            'userRole' => auth()->user()?->role,
            'userPermissions' => [
                'canApprove' => auth()->user()?->canApproveDisbursements() ?? false,
                'canPost' => auth()->user()?->canPostDisbursements() ?? false,
                'isCashier' => auth()->user()?->isCashier() ?? false,
            ],
        ]);
    }

    /**
     * Statuses the current user may set directly on create/edit. The approval
     * outcomes (approved, posted, rejected, returned_for_revision) are only
     * reachable through the dedicated Head of Finance endpoints — allowing them
     * here would let a Cashier finalize a disbursement without approval.
     */
    protected function allowedStatuses(): array
    {
        if (auth()->user()?->canApproveDisbursements()) {
            return ['draft', 'for_release', 'for_approval', 'approved', 'posted', 'rejected', 'returned_for_revision'];
        }

        return ['draft', 'for_release', 'for_approval'];
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
            'status' => 'required|in:' . implode(',', $this->allowedStatuses()),
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string',
        ], [
            'expense_id.required' => 'Please select a linked expense.',
            'status.in' => 'You are not authorized to set this status.',
        ]);

        $lastDsb = Disbursement::latest('id')->first();
        $nextNum = $lastDsb ? intval(substr($lastDsb->disbursement_no, 3)) + 1 : 1;
        $validated['disbursement_no'] = 'DSB' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
        $validated['prepared_by_id'] = auth()->id();

        // If Cashier sets status to for_approval directly upon saving release details
        if (auth()->user()?->isCashier() && in_array($validated['status'], ['for_release', 'for_approval'])) {
            $validated['status'] = 'for_approval';
            $validated['released_by_id'] = auth()->id();
            $validated['submitted_by_id'] = auth()->id();
        }

        $dsb = Disbursement::create($validated);

        AuditTrail::log($dsb, 'created', auth()->user(), $validated['remarks'] ?? 'Disbursement record created.');

        if ($dsb->status === 'posted') {
            $this->syncExpensePaidAmount($dsb->expense_id);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement created.');
    }

    public function update(Request $request, Disbursement $disbursement)
    {
        // Once approved or posted, only the Head of Finance may modify the record —
        // otherwise a Cashier could revert a finalized disbursement back to draft.
        if (in_array($disbursement->status, ['approved', 'posted']) && !auth()->user()?->canApproveDisbursements()) {
            abort(403, 'Only the Head of Finance can modify an approved or posted disbursement.');
        }

        $oldExpenseId = $disbursement->expense_id;

        $validated = $request->validate([
            'expense_id' => 'required|exists:expenses,id',
            'description' => 'required|string|max:255',
            'source' => 'required|string|max:255',
            'pay_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:check,cash,bank_transfer',
            'date_encoded' => 'required|date',
            'status' => 'required|in:' . implode(',', $this->allowedStatuses()),
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string',
        ], [
            'expense_id.required' => 'Please select a linked expense.',
            'status.in' => 'You are not authorized to set this status.',
        ]);

        // Same escalation as store(): a Cashier saving release details goes straight to for_approval
        if (auth()->user()?->isCashier() && in_array($validated['status'], ['for_release', 'for_approval'])) {
            $validated['status'] = 'for_approval';
            $validated['released_by_id'] = auth()->id();
            $validated['submitted_by_id'] = auth()->id();
        }

        $disbursement->update($validated);

        AuditTrail::log($disbursement, 'modified', auth()->user(), $validated['remarks'] ?? 'Disbursement details updated.');

        $this->syncExpensePaidAmount($oldExpenseId);
        if ($disbursement->expense_id && $disbursement->expense_id !== $oldExpenseId) {
            $this->syncExpensePaidAmount($disbursement->expense_id);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement updated.');
    }

    public function submitForApproval(Request $request, Disbursement $disbursement)
    {
        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $disbursement->update([
            'status' => 'for_approval',
            'released_by_id' => auth()->id(),
            'submitted_by_id' => auth()->id(),
            'remarks' => $request->remarks ?: 'Submitted to Head of Finance for approval.',
        ]);

        AuditTrail::log($disbursement, 'submitted', auth()->user(), $request->remarks ?: 'Released & submitted to Head of Finance for approval.');

        return redirect()->route('disbursements.index')->with('success', 'Disbursement submitted for approval.');
    }

    public function approve(Request $request, Disbursement $disbursement)
    {
        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $disbursement->update([
            'status' => 'approved',
            'approved_by_id' => auth()->id(),
            'date_approved' => now(),
            'remarks' => $request->remarks ?: 'Approved by Head of Finance.',
        ]);

        AuditTrail::log($disbursement, 'approved', auth()->user(), $request->remarks ?: 'Approved by Head of Finance.');

        return redirect()->route('disbursements.index')->with('success', 'Disbursement approved.');
    }

    public function postDisbursement(Request $request, Disbursement $disbursement)
    {
        if ($disbursement->status !== 'approved') {
            return redirect()->back()->with('error', 'Disbursement must be approved before posting.');
        }

        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $disbursement->update([
            'status' => 'posted',
            'posted_by_id' => auth()->id(),
            'remarks' => $request->remarks ?: 'Posted to general ledger & official expenditures updated.',
        ]);

        AuditTrail::log($disbursement, 'posted', auth()->user(), $request->remarks ?: 'Posted to general ledger.');

        $this->syncExpensePaidAmount($disbursement->expense_id);

        return redirect()->route('disbursements.index')->with('success', 'Disbursement posted successfully. Expenditures updated.');
    }

    public function reject(Request $request, Disbursement $disbursement)
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $disbursement->update([
            'status' => 'rejected',
            'rejected_by_id' => auth()->id(),
            'remarks' => $request->remarks,
        ]);

        AuditTrail::log($disbursement, 'rejected', auth()->user(), $request->remarks);

        $this->syncExpensePaidAmount($disbursement->expense_id);

        return redirect()->route('disbursements.index')->with('success', 'Disbursement rejected.');
    }

    public function returnForRevision(Request $request, Disbursement $disbursement)
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $disbursement->update([
            'status' => 'returned_for_revision',
            'remarks' => $request->remarks,
        ]);

        AuditTrail::log($disbursement, 'returned_for_revision', auth()->user(), $request->remarks);

        return redirect()->route('disbursements.index')->with('success', 'Disbursement returned for revision.');
    }

    public function destroy(Disbursement $disbursement)
    {
        // Deleting a finalized disbursement reverses official expenditure — Head of Finance only.
        if (in_array($disbursement->status, ['approved', 'posted']) && !auth()->user()?->canApproveDisbursements()) {
            abort(403, 'Only the Head of Finance can delete an approved or posted disbursement.');
        }

        $expenseId = $disbursement->expense_id;
        AuditTrail::log($disbursement, 'deleted', auth()->user(), 'Disbursement deleted.');

        $disbursement->delete();

        if ($expenseId) {
            $this->syncExpensePaidAmount($expenseId);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement deleted.');
    }

    protected function syncExpensePaidAmount(?int $expenseId)
    {
        if (!$expenseId) return;

        $expense = Expense::find($expenseId);
        if ($expense) {
            // CRITICAL BUSINESS RULE: Only POSTED disbursements update official paid expenditure amounts
            $totalPaid = Disbursement::where('expense_id', $expenseId)
                ->where('status', 'posted')
                ->sum('amount');

            $expense->update(['paid' => $totalPaid]);

            // Sync budget item expenditure as well
            if ($expense->date_encoded && $expense->particular_id) {
                $year = date('Y', strtotime($expense->date_encoded));
                $budget = \App\Models\AnnualBudget::where('year', $year)->first();
                if ($budget) {
                    $budgetItem = \App\Models\BudgetItem::where('budget_id', $budget->id)
                        ->where('particular_id', $expense->particular_id)
                        ->first();
                    if ($budgetItem) {
                        $postedTotal = Expense::whereYear('date_encoded', $year)
                            ->where('particular_id', $expense->particular_id)
                            ->where('status', '!=', 'cancelled')
                            ->sum('paid');
                        $budgetItem->update(['expenditure' => $postedTotal]);
                    }
                }
            }
        }
    }
}
