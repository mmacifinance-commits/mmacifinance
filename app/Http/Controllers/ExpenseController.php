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
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\BudgetUtilizationService;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::with([
            'category',
            'particular.department',
            'disbursements',
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

        $budgetedCategories = BudgetCategory::query()
            ->whereHas('budgetItems.budget', function ($query) use ($availableYears) {
                $query->whereIn('year', $availableYears);
            })
            ->with(['budgetItems' => function ($query) use ($availableYears) {
                $query
                    ->select(['id', 'budget_id', 'category_id'])
                    ->whereHas('budget', fn ($budgetQuery) => $budgetQuery->whereIn('year', $availableYears))
                    ->with('budget:id,year');
            }])
            ->orderBy('name')
            ->get();

        // BudgetItem has calculated attributes that query posted workflow totals.
        // The expense category picker only needs the related fiscal years, so do
        // not serialize those expensive attributes for every dropdown option.
        $budgetedCategories->each(function (BudgetCategory $category) {
            $category->budgetItems->each(fn (BudgetItem $item) => $item->setAppends([]));
        });

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => BudgetCategory::all(),
            'budgetedCategories' => $budgetedCategories,
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

        $categoryHasAppropriation = BudgetCategory::whereKey($validated['category_id'])
            ->whereHas('budgetItems.budget', function ($query) use ($year) {
                $query->where('year', $year);
            })
            ->exists();

        if (! $categoryHasAppropriation) {
            throw ValidationException::withMessages([
                'category_id' => "Selected category has no appropriation for FY {$year}. Please choose a category with an approved budget allocation.",
            ]);
        }

        $validated['status'] = strtolower(trim((string) ($validated['status'] ?? 'pending')));
        if ($request->header('X-Offline-Sync')) {
            $validated['status'] = 'pending';
            $validated['date_approved'] = null;
        }
        if (!in_array($validated['status'], ['pending', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'New expenditures must start as Pending or Cancelled. Use the workflow actions to submit and approve them.',
            ]);
        }

        $lastExpense = Expense::latest('id')->first();
        $nextNum = $lastExpense ? intval(substr($lastExpense->ref_no, 3)) + 1 : 1;
        $validated['ref_no'] = 'EXP' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
        $validated['budget_item_id'] = $this->resolveBudgetItemId($validated);

        $expense = Expense::create($validated);

        $warning = $this->budgetOverrunWarning($expense);

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $expense->id, 'resource' => 'expense', 'record' => $expense->fresh()], 201);
        }

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

        $categoryHasAppropriation = BudgetCategory::whereKey($validated['category_id'])
            ->whereHas('budgetItems.budget', function ($query) use ($year) {
                $query->where('year', $year);
            })
            ->exists();

        if (! $categoryHasAppropriation) {
            throw ValidationException::withMessages([
                'category_id' => "Selected category has no appropriation for FY {$year}. Please choose a category with an approved budget allocation.",
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

        $validated['budget_item_id'] = $this->resolveBudgetItemId($validated);
        $expense->update($validated);

        $warning = $this->budgetOverrunWarning($expense);

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $expense->id, 'resource' => 'expense', 'record' => $expense->fresh()]);
        }

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

    public function exportCsv()
    {
        $fileName = 'expenses-export-' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ref_no', 'description', 'category_id', 'particular_id', 'amount', 'date_encoded', 'date_approved', 'status', 'notes']);

            Expense::query()->orderBy('id')->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $expense) {
                    fputcsv($handle, [
                        $expense->ref_no,
                        $expense->description,
                        $expense->category_id,
                        $expense->particular_id,
                        $expense->amount,
                        optional($expense->date_encoded)->format('Y-m-d'),
                        optional($expense->date_approved)->format('Y-m-d'),
                        $expense->status,
                        $expense->notes,
                    ]);
                }
            });

            fclose($handle);
        };

        return Response::streamDownload($callback, $fileName, $headers);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['csv_file' => 'Unable to read the uploaded CSV file.']);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV file is empty.']);
        }

        $header = array_map(fn ($value) => trim((string) $value), $header);
        $required = ['ref_no', 'description', 'category', 'account_title', 'amount', 'date_encoded', 'date_approved', 'status', 'notes'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                fclose($handle);
                return back()->withErrors(['csv_file' => "Missing required column: {$column}"]);
            }
        }

        $index = array_flip($header);
        $allowedStatuses = ['pending', 'cancelled'];
        $rows = [];
        $years = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $categoryRaw = trim((string) ($row[$index['category']] ?? ''));
            $particularRaw = trim((string) ($row[$index['account_title']] ?? ''));

            $rows[] = [
                'ref_no' => trim((string) ($row[$index['ref_no']] ?? '')),
                'category_raw' => $categoryRaw,
                'particular_raw' => $particularRaw,
                'description' => trim((string) ($row[$index['description']] ?? '')),
                'amount' => (float) ($row[$index['amount']] ?? 0),
                'date_encoded' => trim((string) ($row[$index['date_encoded']] ?? '')),
                'date_approved' => trim((string) ($row[$index['date_approved']] ?? '')),
                'status' => strtolower(trim((string) ($row[$index['status']] ?? 'pending'))),
                'notes' => trim((string) ($row[$index['notes']] ?? '')),
            ];
        }

        fclose($handle);

        foreach ($rows as $i => $row) {
            if ($row['ref_no'] === '' || $row['description'] === '' || $row['category_raw'] === '' || $row['particular_raw'] === '' || $row['date_encoded'] === '') {
                return back()->withErrors(['csv_file' => 'Row ' . ($i + 2) . ' is missing required data.']);
            }

            if (!in_array($row['status'], $allowedStatuses, true)) {
                return back()->withErrors(['csv_file' => 'Row ' . ($i + 2) . ' has an invalid status.']);
            }

            $year = (int) date('Y', strtotime($row['date_encoded']));
            $years[] = $year;

            if (!AnnualBudget::where('year', $year)->exists()) {
                return back()->withErrors(['csv_file' => "Annual budget for FY {$year} is required before importing expenditures."]);
            }

            $category = $this->resolveExpenseCategory($row['category_raw']);
            if (! $category) {
                return back()->withErrors(['csv_file' => "Row " . ($i + 2) . " references an unknown category: {$row['category_raw']}"]);
            }

            $particular = $this->resolveExpenseParticular($row['particular_raw'], $category->id);
            if (! $particular) {
                return back()->withErrors(['csv_file' => "Row " . ($i + 2) . " references an unknown account title: {$row['particular_raw']}"]);
            }

            $rows[$i]['category_id'] = $category->id;
            $rows[$i]['particular_id'] = $particular->id;
            $rows[$i]['budget_item_id'] = $this->resolveBudgetItemId([
                'category_id' => $category->id,
                'particular_id' => $particular->id,
                'date_encoded' => $row['date_encoded'],
            ]);

            $categoryHasAppropriation = BudgetCategory::whereKey($category->id)
                ->whereHas('budgetItems.budget', function ($query) use ($year) {
                    $query->where('year', $year);
                })
                ->exists();

            if (! $categoryHasAppropriation) {
                return back()->withErrors(['csv_file' => "Category {$category->name} has no appropriation for FY {$year}."]);
            }
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $expense = Expense::firstOrNew(['ref_no' => $row['ref_no']]);

                $isNew = !$expense->exists;
                $expense->ref_no = $row['ref_no'];
                $expense->description = $row['description'];
                $expense->category_id = $row['category_id'];
                $expense->particular_id = $row['particular_id'];
                $expense->budget_item_id = $row['budget_item_id'];
                $expense->amount = $row['amount'];
                $expense->date_encoded = $row['date_encoded'];
                $expense->date_approved = $row['date_approved'] !== '' ? $row['date_approved'] : null;
                $expense->status = $row['status'];
                $expense->notes = $row['notes'] !== '' ? $row['notes'] : null;
                $expense->save();

                $isNew ? $created++ : $updated++;
            }
        });

        return redirect()->back()->with('success', "Expense CSV imported successfully. Created: {$created}, Updated: {$updated}");
    }

    protected function resolveExpenseCategory(string $raw): ?BudgetCategory
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return BudgetCategory::find((int) $value);
        }

        return BudgetCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->first();
    }

    protected function resolveBudgetItemId(array $expenseData): ?int
    {
        $budgetItem = app(BudgetUtilizationService::class)
            ->resolveBudgetItem(
                (int) $expenseData['category_id'],
                (int) $expenseData['particular_id'],
                (string) $expenseData['date_encoded']
            );

        if (! $budgetItem) {
            $year = (int) date('Y', strtotime((string) $expenseData['date_encoded']));
            $month = date('F', strtotime((string) $expenseData['date_encoded']));
            throw ValidationException::withMessages([
                'particular_id' => "No unique monthly budget allocation matches this account title for {$month} FY {$year}.",
            ]);
        }

        return $budgetItem->id;
    }

    protected function resolveExpenseParticular(string $raw, int $categoryId): ?BudgetParticular
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $query = BudgetParticular::query()->where('category_id', $categoryId);

        if (ctype_digit($value)) {
            return $query->whereKey((int) $value)->first();
        }

        return $query->where(function ($subQuery) use ($value) {
            $lower = mb_strtolower($value);
            $subQuery->whereRaw('LOWER(particular) = ?', [$lower])
                ->orWhereRaw('LOWER(account_name) = ?', [$lower]);
        })->first();
    }

    protected function budgetOverrunWarning(Expense $expense): ?string
    {
        if (!$expense->date_encoded || !$expense->particular_id) {
            return null;
        }

        $budgetItem = $expense->budgetItem;

        if (!$budgetItem) {
            return null;
        }

        $totalPaid = app(BudgetUtilizationService::class)->expenditureForItem($budgetItem);

        $budgetAmount = (float) $budgetItem->appropriation;
        if ($totalPaid > $budgetAmount) {
            $over = $totalPaid - $budgetAmount;
            return 'This expense is over the budget by ' . number_format($over, 2) . '. It will still be saved so you can monitor the overrun.';
        }

        return null;
    }
}
