<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\AuditTrail;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Services\CashFlowService;
use App\Support\SpreadsheetImportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DisbursementController extends Controller
{
    public function __construct(private readonly CashFlowService $cashFlow) {}

    public function index(Request $request)
    {
        $yearExpression = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', date_encoded) AS INTEGER)"
            : 'YEAR(date_encoded)';

        $disbursementQuery = Disbursement::with([
            'expense.category',
            'expense.particular',
            'expense.budgetItem.budget',
            'preparedBy',
            'releasedBy',
            'submittedBy',
            'approvedBy',
            'postedBy',
            'auditTrails',
        ])->latest();

        $disbursements = $disbursementQuery->paginate(25)->withQueryString();
        $yearsFromDsb = Disbursement::query()
            ->selectRaw("{$yearExpression} as year")
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
            ->map(fn ($d) => (int) date('Y', strtotime($d)))
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

        $fiscalPeriods = AnnualBudget::query()->orderByDesc('start_date')->get(['id', 'year', 'start_date', 'end_date', 'ref_no']);

        return Inertia::render('Disbursements/Index', [
            'disbursements' => $disbursements,
            'expenses' => Expense::with('budgetItem.budget:id,year,start_date,end_date')
                ->select(
                    'id',
                    'ref_no',
                    'description',
                    'budget_item_id',
                    'amount',
                    'paid',
                    'date_encoded',
                    'created_at',
                    'status'
                )->latest('date_encoded')->get(),
            'budgetYears' => AnnualBudget::pluck('year')->values()->toArray(),
            'fiscalPeriods' => $fiscalPeriods,
            'cashFlowByFiscalPeriod' => $fiscalPeriods
                ->mapWithKeys(fn (AnnualBudget $budget) => [$budget->id => $this->cashFlow->summary($budget)])
                ->all(),
            'defaultFiscalPeriodId' => AnnualBudget::query()
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->value('id') ?? AnnualBudget::query()->orderByDesc('start_date')->value('id'),
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

    protected function ensureDatesWithinLinkedFiscalPeriod(Expense $expense, string $disbursementDate): void
    {
        $budget = $expense->loadMissing('budgetItem.budget')->budgetItem?->budget;

        if (! $budget) {
            throw ValidationException::withMessages([
                'expense_id' => 'The selected expense is not linked to a monthly budget allocation.',
            ]);
        }

        if (! $budget->containsDate($expense->date_encoded)) {
            throw ValidationException::withMessages([
                'expense_id' => "The expense date falls outside {$budget->fiscal_year_label} ({$budget->period_label}).",
            ]);
        }

        if (! $budget->containsDate($disbursementDate)) {
            throw ValidationException::withMessages([
                'date_encoded' => "The disbursement date must fall within {$budget->fiscal_year_label} ({$budget->period_label}).",
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
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:check,cash,bank_transfer',
            'date_encoded' => 'required|date',
            'status' => 'required|in:'.implode(',', $this->allowedStatuses()),
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string',
        ], [
            'expense_id.required' => 'Please select a linked expense.',
            'status.in' => 'You are not authorized to set this status.',
        ]);

        $selectedExpense = Expense::findOrFail($validated['expense_id']);
        $this->ensureApprovedLinkedExpense($selectedExpense);
        $this->ensureDatesWithinLinkedFiscalPeriod($selectedExpense, $validated['date_encoded']);

        $dsb = DB::transaction(function () use ($request, $validated, $selectedExpense) {
            $draftDisbursement = new Disbursement($validated);
            $draftDisbursement->expense()->associate($selectedExpense);
            $this->cashFlow->ensureSufficientCashForDisbursement($draftDisbursement);

            $lastDsb = Disbursement::latest('id')->lockForUpdate()->first();
            $nextNum = $lastDsb ? intval(substr($lastDsb->disbursement_no, 3)) + 1 : 1;
            $validated['disbursement_no'] = 'DSB'.str_pad($nextNum, 8, '0', STR_PAD_LEFT);
            $validated['prepared_by_id'] = auth()->id();

            if ($request->header('X-Offline-Sync')) {
                $validated['status'] = 'draft';
                unset($validated['released_by_id'], $validated['submitted_by_id'], $validated['approved_by_id'], $validated['posted_by_id']);
            }

            // If Cashier sets status to for_approval directly upon saving release details
            if (! $request->header('X-Offline-Sync') && auth()->user()?->isCashier() && in_array($validated['status'], ['for_release', 'for_approval'])) {
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

            return $dsb;
        });

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $dsb->id, 'resource' => 'disbursement', 'record' => $dsb->fresh()], 201);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement created.');
    }

    public function update(Request $request, Disbursement $disbursement)
    {
        // Once approved or posted, only the Head of Finance may modify the record —
        // otherwise a Cashier could revert a finalized disbursement back to draft.
        if (in_array($disbursement->status, ['approved', 'posted']) && ! auth()->user()?->canApproveDisbursements()) {
            abort(403, 'Only the Head of Finance can modify an approved or posted disbursement.');
        }

        $oldExpenseId = $disbursement->expense_id;

        $validated = $request->validate([
            'expense_id' => 'required|exists:expenses,id',
            'description' => 'required|string|max:255',
            'source' => 'required|string|max:255',
            'pay_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:check,cash,bank_transfer',
            'date_encoded' => 'required|date',
            'status' => 'required|in:'.implode(',', $this->allowedStatuses()),
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string',
        ], [
            'expense_id.required' => 'Please select a linked expense.',
            'status.in' => 'You are not authorized to set this status.',
        ]);

        if ($request->header('X-Offline-Sync')) {
            if (! in_array($disbursement->status, ['draft', 'for_release'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'Only draft disbursements can be edited from the offline queue.',
                ]);
            }
            $validated['status'] = 'draft';
        }

        $selectedExpense = Expense::findOrFail($validated['expense_id']);
        $this->ensureApprovedLinkedExpense($selectedExpense);
        $this->ensureDatesWithinLinkedFiscalPeriod($selectedExpense, $validated['date_encoded']);

        // Same escalation as store(): a Cashier saving release details goes straight to for_approval
        if (auth()->user()?->isCashier() && in_array($validated['status'], ['for_release', 'for_approval'])) {
            $validated['status'] = 'for_approval';
            $validated['released_by_id'] = auth()->id();
            $validated['submitted_by_id'] = auth()->id();
        }

        if ($validated['status'] === 'for_release') {
            $validated['released_by_id'] = $validated['released_by_id'] ?? auth()->id();
        }

        DB::transaction(function () use ($disbursement, $validated, $selectedExpense) {
            $draftDisbursement = $disbursement->replicate();
            $draftDisbursement->id = $disbursement->id;
            $draftDisbursement->exists = true;
            $draftDisbursement->fill($validated);
            $draftDisbursement->expense()->associate($selectedExpense);
            $this->cashFlow->ensureSufficientCashForDisbursement($draftDisbursement);

            $disbursement->update($validated);
        });

        AuditTrail::log($disbursement, 'modified', auth()->user(), $validated['remarks'] ?? 'Disbursement details updated.');

        $this->syncExpensePaidAmount($oldExpenseId);
        if ($disbursement->expense_id && $disbursement->expense_id !== $oldExpenseId) {
            $this->syncExpensePaidAmount($disbursement->expense_id);
        }

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $disbursement->id, 'resource' => 'disbursement', 'record' => $disbursement->fresh()]);
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement updated.');
    }

    public function submitForApproval(Request $request, Disbursement $disbursement)
    {
        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        if (! auth()->user()?->canManageDisbursements()) {
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

        if (! auth()->user()?->canApproveDisbursements()) {
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
        if (! auth()->user()?->canPostDisbursements()) {
            abort(403, 'Only the Head of Finance can post disbursements.');
        }

        if ($disbursement->status !== 'approved') {
            return redirect()->back()->with('error', 'Disbursement must be approved before posting.');
        }

        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($disbursement, $request) {
                $lockedDisbursement = Disbursement::query()
                    ->with('expense.budgetItem.budget')
                    ->lockForUpdate()
                    ->findOrFail($disbursement->id);

                if ($lockedDisbursement->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'status' => 'Disbursement must be approved before posting.',
                    ]);
                }

                $this->cashFlow->ensureSufficientCashForPosting($lockedDisbursement);

                $lockedDisbursement->update([
                    'status' => 'posted',
                    'posted_by_id' => auth()->id(),
                    'remarks' => $request->remarks ?: 'Posted to general ledger & official expenditures updated.',
                ]);

                AuditTrail::log($lockedDisbursement, 'posted', auth()->user(), $request->remarks ?: 'Posted to general ledger.');

                $this->syncExpensePaidAmount($lockedDisbursement->expense_id);
            });
        } catch (ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('disbursements.index')->with('success', 'Disbursement posted successfully. Expenditures updated.');
    }

    public function reject(Request $request, Disbursement $disbursement)
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        if (! auth()->user()?->canApproveDisbursements()) {
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

        if (! auth()->user()?->canApproveDisbursements()) {
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
        if (in_array($disbursement->status, ['approved', 'posted']) && ! auth()->user()?->canApproveDisbursements()) {
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

    public function exportCsv()
    {
        $fileName = 'disbursements-export-'.now()->format('Y-m-d_His');
        $rows = [['disbursement_no', 'expense_ref_no', 'description', 'source', 'pay_to', 'amount', 'method', 'date_encoded', 'status', 'notes', 'remarks']];

        Disbursement::with('expense:id,ref_no')->orderBy('id')->chunk(200, function ($rowsChunk) use (&$rows) {
            foreach ($rowsChunk as $dsb) {
                $rows[] = [
                    $dsb->disbursement_no,
                    $dsb->expense?->ref_no,
                    $dsb->description,
                    $dsb->source,
                    $dsb->pay_to,
                    $dsb->amount,
                    $dsb->method,
                    optional($dsb->date_encoded)->format('Y-m-d'),
                    $dsb->status,
                    $dsb->notes,
                    $dsb->remarks,
                ];
            }
        });

        return SpreadsheetImportExport::downloadXlsx($fileName, $rows);
    }

    public function importCsv(Request $request)
    {
        $request->validate(SpreadsheetImportExport::validationRules('csv_file', true));

        try {
            [$header, $rows] = SpreadsheetImportExport::readRows($request->file('csv_file'));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['csv_file' => $exception->getMessage()]);
        }

        if (empty($header)) {
            return back()->withErrors(['csv_file' => 'CSV/Excel file is empty.']);
        }

        $required = ['disbursement_no', 'expense_ref_no', 'description', 'source', 'pay_to', 'amount', 'method', 'date_encoded', 'status', 'notes', 'remarks'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                return back()->withErrors(['csv_file' => "Missing required column: {$column}"]);
            }
        }

        $index = array_flip($header);
        $allowedStatuses = ['draft', 'for_release', 'for_approval'];
        $parsedRows = [];

        foreach ($rows as $row) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $parsedRows[] = [
                'line' => count($parsedRows) + 2,
                'disbursement_no' => trim((string) ($row[$index['disbursement_no']] ?? '')),
                'expense_ref_no' => trim((string) ($row[$index['expense_ref_no']] ?? '')),
                'description' => trim((string) ($row[$index['description']] ?? '')),
                'source' => trim((string) ($row[$index['source']] ?? '')),
                'pay_to' => trim((string) ($row[$index['pay_to']] ?? '')),
                'amount' => (float) ($row[$index['amount']] ?? 0),
                'method' => trim((string) ($row[$index['method']] ?? 'check')),
                'date_encoded' => trim((string) ($row[$index['date_encoded']] ?? '')),
                'status' => strtolower(trim((string) ($row[$index['status']] ?? 'draft'))),
                'notes' => trim((string) ($row[$index['notes']] ?? '')),
                'remarks' => trim((string) ($row[$index['remarks']] ?? '')),
            ];
        }

        foreach ($parsedRows as $i => $row) {
            if ($row['disbursement_no'] === '' || $row['expense_ref_no'] === '' || $row['description'] === '' || $row['source'] === '' || $row['pay_to'] === '' || $row['date_encoded'] === '') {
                return back()->withErrors(['csv_file' => 'Row '.($i + 2).' is missing required data.']);
            }

            if (! in_array($row['status'], $allowedStatuses, true)) {
                return back()->withErrors(['csv_file' => 'Row '.($i + 2).' has an invalid status.']);
            }

            if ($row['amount'] <= 0) {
                return back()->withErrors(['csv_file' => 'Row '.($i + 2).' must have an amount greater than zero.']);
            }

            $expense = Expense::where('ref_no', $row['expense_ref_no'])->where('status', 'approved')->first();
            if (! $expense) {
                return back()->withErrors(['csv_file' => 'Row '.($i + 2)." requires an approved expense with ref_no {$row['expense_ref_no']} before importing disbursements."]);
            }

            try {
                $this->ensureDatesWithinLinkedFiscalPeriod($expense, $row['date_encoded']);
            } catch (ValidationException $exception) {
                return back()->withErrors([
                    'csv_file' => 'Row '.($i + 2).' cannot be imported: '.$exception->validator->errors()->first(),
                ]);
            }
        }

        $created = 0;
        $updated = 0;

        try {
            DB::transaction(function () use ($parsedRows, &$created, &$updated) {
                foreach ($parsedRows as $row) {
                    $expense = Expense::where('ref_no', $row['expense_ref_no'])->where('status', 'approved')->first();
                    if (! $expense) {
                        continue;
                    }

                    $disbursement = Disbursement::firstOrNew(['disbursement_no' => $row['disbursement_no']]);
                    $isNew = ! $disbursement->exists;
                    $disbursement->disbursement_no = $row['disbursement_no'];
                    $disbursement->expense_id = $expense->id;
                    $disbursement->description = $row['description'];
                    $disbursement->source = $row['source'];
                    $disbursement->pay_to = $row['pay_to'];
                    $disbursement->amount = $row['amount'];
                    $disbursement->method = in_array($row['method'], ['check', 'cash', 'bank_transfer'], true) ? $row['method'] : 'check';
                    $disbursement->date_encoded = $row['date_encoded'];
                    $disbursement->status = $row['status'];
                    $disbursement->notes = $row['notes'] !== '' ? $row['notes'] : null;
                    $disbursement->remarks = $row['remarks'] !== '' ? $row['remarks'] : null;
                    $disbursement->prepared_by_id = auth()->id();
                    $disbursement->expense()->associate($expense);

                    try {
                        $this->cashFlow->ensureSufficientCashForDisbursement($disbursement);
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([
                            'csv_file' => 'Row '.$row['line'].' cannot be imported: '.$exception->validator->errors()->first(),
                        ]);
                    }

                    $disbursement->save();

                    AuditTrail::log($disbursement, $isNew ? 'created' : 'modified', auth()->user(), $row['remarks'] !== '' ? $row['remarks'] : ($isNew ? 'Disbursement imported from CSV/Excel.' : 'Disbursement updated from CSV/Excel.'));
                    if ($disbursement->status === 'posted') {
                        $this->syncExpensePaidAmount($disbursement->expense_id);
                    }

                    $isNew ? $created++ : $updated++;
                }
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->back()->with('success', "Disbursement CSV/Excel imported successfully. Created: {$created}, Updated: {$updated}");
    }

    protected function syncExpensePaidAmount(?int $expenseId)
    {
        if (! $expenseId) {
            return;
        }

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
