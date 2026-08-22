<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\AnnualBudget;
use App\Models\Disbursement;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DisbursementController extends Controller
{
    public function index(Request $request)
    {
        $disbursementQuery = Disbursement::with([
            'expense.category',
            'expense.particular',
            'preparedBy',
            'releasedBy',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'postedBy',
            'auditTrails',
        ])->latest();

        $disbursements = $disbursementQuery->paginate(25)->withQueryString();
        $yearsFromDsb = Disbursement::query()
            ->selectRaw('YEAR(date_encoded) as year')
            ->distinct()
            ->pluck('year')
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();

        $pageItems = collect($disbursements->items());
        $yearsFromDsb = $yearsFromDsb->concat($pageItems->pluck('date_encoded')
            ->filter()
            ->map(fn($d) => (int) date('Y', strtotime($d)))
            ->unique()
            ->values())->unique()->sortDesc()->values();

        $budgetYears = AnnualBudget::pluck('year');
        $currentYear = (int) date('Y');

        $availableYears = $yearsFromDsb->concat($budgetYears)
            ->push($currentYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $defaultYear = $yearsFromDsb->first() ?? $budgetYears->sortDesc()->values()->first() ?? $currentYear;

        return Inertia::render('Disbursements/Index', [
            'disbursements' => $disbursements,
            'expenses' => Expense::select(
                'id',
                'ref_no',
                'description',
                'amount',
                'paid',
                'date_encoded',
                'created_at',
                'status'
            )->latest('date_encoded')->get(),
            'budgetYears' => AnnualBudget::pluck('year')->values()->toArray(),
            'availableYears' => $availableYears,
            'defaultYear' => $defaultYear,
            'userRole' => auth()->user()?->role,
            'userPermissions' => [
                'canManageDisbursements' => auth()->user()?->canManageDisbursements() ?? false,
                'canApprove' => auth()->user()?->canApproveDisbursements() ?? false,
                'canPost' => auth()->user()?->canPostDisbursements() ?? false,
                'isCashier' => auth()->user()?->isCashier() ?? false,
                'isSuperAdmin' => auth()->user()?->isSuperAdmin() ?? false,
            ],
        ]);
    }

    /**
     * Statuses the current user may set directly on create/edit. The approval
     * outcomes (approved, posted, rejected, returned_for_revision) are only
     * reachable through the dedicated Head of Finance endpoints — allowing them
     * here would let a user finalize a disbursement without approval.
     */
    protected function allowedStatuses(): array
    {
        return ['draft', 'for_release', 'for_approval'];
    }

    protected function ensureApprovedLinkedExpense(Expense $expense): void
    {
        if (strtolower((string) $expense->status) !== 'approved') {
            throw ValidationException::withMessages([
                'expense_id' => 'Only approved expenses can be released as disbursements.',
            ]);
        }
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

        $selectedExpense = Expense::findOrFail($validated['expense_id']);
        $this->ensureApprovedLinkedExpense($selectedExpense);
        $expenseYear = (int) date('Y', strtotime($selectedExpense->date_encoded));
        $releaseYear = (int) date('Y', strtotime($validated['date_encoded']));

        if (!AnnualBudget::where('year', $expenseYear)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'expense_id' => "No annual budget exists for FY {$expenseYear}. Please create the annual budget first.",
            ]);
        }

        if (!AnnualBudget::where('year', $releaseYear)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_encoded' => "No annual budget exists for FY {$releaseYear}. Please create the annual budget first.",
            ]);
        }

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

        if ($validated['status'] === 'for_release') {
            $validated['released_by_id'] = $validated['released_by_id'] ?? auth()->id();
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

        $selectedExpense = Expense::findOrFail($validated['expense_id']);
        $this->ensureApprovedLinkedExpense($selectedExpense);
        $expenseYear = (int) date('Y', strtotime($selectedExpense->date_encoded));
        $releaseYear = (int) date('Y', strtotime($validated['date_encoded']));

        if (!AnnualBudget::where('year', $expenseYear)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'expense_id' => "No annual budget exists for FY {$expenseYear}. Please create the annual budget first.",
            ]);
        }

        if (!AnnualBudget::where('year', $releaseYear)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_encoded' => "No annual budget exists for FY {$releaseYear}. Please create the annual budget first.",
            ]);
        }

        // Same escalation as store(): a Cashier saving release details goes straight to for_approval
        if (auth()->user()?->isCashier() && in_array($validated['status'], ['for_release', 'for_approval'])) {
            $validated['status'] = 'for_approval';
            $validated['released_by_id'] = auth()->id();
            $validated['submitted_by_id'] = auth()->id();
        }

        if ($validated['status'] === 'for_release') {
            $validated['released_by_id'] = $validated['released_by_id'] ?? auth()->id();
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

        if (!auth()->user()?->canManageDisbursements()) {
            abort(403, 'You are not allowed to submit disbursements for approval.');
        }

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

        if (!auth()->user()?->canApproveDisbursements()) {
            abort(403, 'Only the Head of Finance can approve disbursements.');
        }

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
        if (!auth()->user()?->canPostDisbursements()) {
            abort(403, 'Only the Head of Finance can post disbursements.');
        }

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

        if (!auth()->user()?->canApproveDisbursements()) {
            abort(403, 'Only the Head of Finance can reject disbursements.');
        }

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

        if (!auth()->user()?->canApproveDisbursements()) {
            abort(403, 'Only the Head of Finance can return disbursements for revision.');
        }

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

            $updates = ['paid' => $totalPaid];

            if ($totalPaid > 0 && $expense->status !== 'cancelled') {
                $updates['status'] = 'posted';
                $updates['date_approved'] = $expense->date_approved ?: now();
            } elseif ($totalPaid <= 0 && $expense->status === 'posted') {
                $updates['status'] = 'pending';
            }

            $expense->update($updates);

            // Budget item expenditure is derived dynamically from posted disbursements.
        }
    }
}
