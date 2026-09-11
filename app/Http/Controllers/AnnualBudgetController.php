<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\AuditTrail;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Income;
use App\Models\IncomeAllocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AnnualBudgetController extends Controller
{
    protected function ensureIncomeExistsForPeriod(string $startDate, string $endDate, string $errorField = 'start_date'): void
    {
        if (! Income::whereBetween('date_encoded', [$startDate, $endDate])->exists()) {
            throw ValidationException::withMessages([
                $errorField => "You must create at least one income record between {$startDate} and {$endDate} before creating appropriation.",
            ]);
        }
    }

    protected function allocatedIncomeTotalForBudget(AnnualBudget $annualBudget): float
    {
        return (float) IncomeAllocation::query()
            ->where('annual_budget_id', $annualBudget->id)
            ->sum('amount');
    }

    protected function incomePoolForBudget(AnnualBudget $annualBudget): array
    {
        return Income::query()
            ->whereBetween('date_encoded', [
                $annualBudget->fiscalStart()->toDateString(),
                $annualBudget->fiscalEnd()->toDateString(),
            ])
            ->orderBy('date_encoded')
            ->orderBy('id')
            ->get()
            ->all();
    }

    protected function allocateIncomeToBudget(AnnualBudget $annualBudget, ?BudgetItem $budgetItem, float $amount): void
    {
        $remainingToAllocate = round($amount, 2);
        if ($remainingToAllocate <= 0) {
            return;
        }

        $availableIncome = $this->incomePoolForBudget($annualBudget);
        $allocatedForBudget = $this->allocatedIncomeTotalForBudget($annualBudget);
        $periodIncomeTotal = (float) Income::whereBetween('date_encoded', [
            $annualBudget->fiscalStart()->toDateString(),
            $annualBudget->fiscalEnd()->toDateString(),
        ])->sum('amount');
        $availableBalance = round($periodIncomeTotal - $allocatedForBudget, 2);

        if ($availableBalance < $remainingToAllocate) {
            throw ValidationException::withMessages([
                'appropriation' => "Not enough remaining income for {$annualBudget->fiscal_year_label}. Available income balance is ".number_format($availableBalance, 2),
            ]);
        }

        foreach ($availableIncome as $income) {
            $usedAmount = (float) IncomeAllocation::where('income_id', $income->id)->sum('amount');
            $incomeBalance = round((float) $income->amount - $usedAmount, 2);
            if ($incomeBalance <= 0) {
                continue;
            }

            $toAllocate = min($incomeBalance, $remainingToAllocate);
            IncomeAllocation::create([
                'income_id' => $income->id,
                'annual_budget_id' => $annualBudget->id,
                'budget_item_id' => $budgetItem?->id,
                'amount' => $toAllocate,
            ]);

            $remainingToAllocate = round($remainingToAllocate - $toAllocate, 2);
            if ($remainingToAllocate <= 0) {
                break;
            }
        }
    }

    protected function reallocateBudgetItemIncome(BudgetItem $item, float $newAmount): void
    {
        IncomeAllocation::where('budget_item_id', $item->id)->delete();

        if ($newAmount > 0) {
            $this->allocateIncomeToBudget($item->budget, $item, $newAmount);
        }
    }

    protected function validateFiscalPeriod(array $validated, ?AnnualBudget $except = null): array
    {
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();

        if ($start->day !== 1) {
            throw ValidationException::withMessages([
                'start_date' => 'The fiscal year must start on the first day of a month.',
            ]);
        }

        if (! $end->isSameDay($end->copy()->endOfMonth())) {
            throw ValidationException::withMessages([
                'end_date' => 'The fiscal year must end on the last day of a month.',
            ]);
        }

        $expectedEnd = $start->copy()->addMonthsNoOverflow(11)->endOfMonth()->startOfDay();
        if (! $end->isSameDay($expectedEnd)) {
            throw ValidationException::withMessages([
                'end_date' => 'A fiscal year must contain exactly 12 consecutive months.',
            ]);
        }

        $overlap = AnnualBudget::query()
            ->overlapping($start->toDateString(), $end->toDateString())
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => "This period overlaps {$overlap->fiscal_year_label} ({$overlap->period_label}).",
            ]);
        }

        return [
            'year' => $start->year,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'semester' => 'Fiscal Year',
        ];
    }

    protected function normalizeHeader(string $header): string
    {
        return trim(Str::lower($header));
    }

    protected function parseMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) preg_replace('/[^\d.\-]/', '', (string) $value);
    }

    protected function parseMonthValue($value): int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 1;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        $normalized = strtolower($raw);
        $map = [
            'jan' => 1, 'january' => 1,
            'feb' => 2, 'february' => 2,
            'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4,
            'may' => 5,
            'jun' => 6, 'june' => 6,
            'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8,
            'sep' => 9, 'sept' => 9, 'september' => 9,
            'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12,
        ];

        return $map[$normalized] ?? 0;
    }

    protected function uniqueCode(string $base, string $prefix, int $maxLength = 20): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $base));
        $clean = $clean !== '' ? $clean : $prefix;
        $clean = substr($clean, 0, max(1, $maxLength - strlen($prefix) - 4));
        $candidate = $prefix.$clean;
        $i = 1;

        while (\App\Models\Department::where('code', $candidate)->exists() || \App\Models\BudgetParticular::where('account_code', $candidate)->exists()) {
            $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $candidate = substr($prefix.$clean, 0, max(1, $maxLength - strlen($suffix))).$suffix;
            $i++;
        }

        return substr($candidate, 0, $maxLength);
    }

    protected function resolveOrCreateDepartment(?string $name): ?\App\Models\Department
    {
        $name = trim((string) $name);
        if ($name === '') {
            $name = 'Administration';
        }

        $department = \App\Models\Department::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        if ($department) {
            return $department;
        }

        $code = $this->uniqueCode($name, 'D', 12);

        return \App\Models\Department::create([
            'name' => $name,
            'code' => $code,
        ]);
    }

    protected function resolveOrCreateCategory(?string $name): \App\Models\BudgetCategory
    {
        $name = trim((string) $name);
        if ($name === '') {
            $name = 'UNCATEGORIZED';
        }

        return \App\Models\BudgetCategory::firstOrCreate(
            ['name' => $name],
            ['description' => $name]
        );
    }

    protected function resolveOrCreateAccountTitle(array $row, \App\Models\BudgetCategory $category, ?\App\Models\Department $department): \App\Models\BudgetParticular
    {
        $accountCode = trim((string) ($row['account_code'] ?? $row['account code'] ?? ''));
        $accountName = trim((string) ($row['account_name'] ?? $row['account title'] ?? $row['particular'] ?? ''));
        $particular = trim((string) ($row['particular'] ?? $row['account_title'] ?? $accountName));
        $description = trim((string) ($row['description'] ?? ''));

        $lookup = null;
        if ($accountCode !== '') {
            $lookup = \App\Models\BudgetParticular::where('account_code', $accountCode)
                ->when($department, fn ($query) => $query->where('department_id', $department->id))
                ->when($category, fn ($query) => $query->where('category_id', $category->id))
                ->first();
        }

        if (! $lookup && $accountName !== '') {
            $lookup = \App\Models\BudgetParticular::whereRaw('LOWER(particular) = ?', [Str::lower($particular ?: $accountName)])
                ->when($department, fn ($query) => $query->where('department_id', $department->id))
                ->when($category, fn ($query) => $query->where('category_id', $category->id))
                ->first();
        }

        if ($lookup) {
            return $lookup;
        }

        $department ??= $this->resolveOrCreateDepartment($row['responsibility_center'] ?? $row['department'] ?? null);

        if ($accountCode === '') {
            $accountCode = $this->uniqueCode($particular ?: $accountName, 'A', 20);
        }

        return \App\Models\BudgetParticular::create([
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => $accountCode,
            'account_name' => $accountName !== '' ? $accountName : ($particular ?: $accountCode),
            'particular' => $particular !== '' ? $particular : ($accountName ?: $accountCode),
            'description' => $description,
        ]);
    }

    public function exportCsv(AnnualBudget $annualBudget)
    {
        $budget = $annualBudget->load(['items.category', 'items.particular.department']);
        $filename = sprintf('annual-budget-%s.csv', $budget->year);

        return response()->streamDownload(function () use ($budget) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'annual_ref_no',
                'fiscal_year',
                'fiscal_year_label',
                'fiscal_start_date',
                'fiscal_end_date',
                'allocation_month',
                'month',
                'budget_category',
                'responsibility_center',
                'account_code',
                'account_title',
                'description',
                'appropriation',
            ]);

            foreach ($budget->items as $item) {
                fputcsv($out, [
                    $budget->ref_no,
                    $budget->year,
                    $budget->fiscal_year_label,
                    $budget->fiscalStart()->toDateString(),
                    $budget->fiscalEnd()->toDateString(),
                    $item->allocation_month?->format('Y-m'),
                    $item->month,
                    $item->category?->name,
                    $item->particular?->department?->name,
                    $item->particular?->account_code,
                    $item->particular?->particular,
                    $item->particular?->description,
                    $item->appropriation,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importCsv(Request $request, AnnualBudget $annualBudget)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $this->ensureIncomeExistsForPeriod(
                $annualBudget->fiscalStart()->toDateString(),
                $annualBudget->fiscalEnd()->toDateString(),
                'csv_file'
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'csv_file' => $e->validator->errors()->first('csv_file')
                    ?: "You must create at least one income record for {$annualBudget->fiscal_year_label} before importing budget rows.",
            ]);
        }

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return back()->with('error', 'Unable to read CSV file.');
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return back()->with('error', 'CSV file is empty.');
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $required = ['budget_category', 'responsibility_center', 'account_title', 'appropriation'];
        foreach ($required as $column) {
            if (! in_array($column, $headers, true)) {
                fclose($handle);

                return back()->with('error', 'CSV is missing required column: '.$column);
            }
        }
        if (! in_array('allocation_month', $headers, true) && ! in_array('month', $headers, true)) {
            fclose($handle);

            return back()->with('error', 'CSV is missing required column: allocation_month (or legacy month).');
        }

        $rows = [];
        $csvTotalAppropriation = 0.0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($headers as $index => $header) {
                $data[$header] = $row[$index] ?? null;
            }

            $amount = $this->parseMoney($data['appropriation'] ?? 0);
            $rows[] = $data;
            $csvTotalAppropriation += $amount;
        }

        rewind($handle);
        fgetcsv($handle); // skip header row

        $availableIncome = (float) Income::whereBetween('date_encoded', [
            $annualBudget->fiscalStart()->toDateString(),
            $annualBudget->fiscalEnd()->toDateString(),
        ])->sum('amount');
        $allocatedIncome = (float) IncomeAllocation::query()
            ->where('annual_budget_id', $annualBudget->id)
            ->sum('amount');
        $remainingIncome = round($availableIncome - $allocatedIncome, 2);

        if ($csvTotalAppropriation > $remainingIncome) {
            fclose($handle);
            throw ValidationException::withMessages([
                'csv_file' => 'Appropriation must not be more than the income. Available income balance is '.number_format($remainingIncome, 2).'.',
            ]);
        }

        $rowsCreated = 0;
        $rowsUpdated = 0;

        DB::transaction(function () use ($rows, $annualBudget, &$rowsCreated, &$rowsUpdated) {
            foreach ($rows as $data) {
                $csvYear = isset($data['fiscal_year']) && $data['fiscal_year'] !== '' ? (int) $data['fiscal_year'] : (int) $annualBudget->year;
                if ($csvYear !== (int) $annualBudget->fiscalStart()->year) {
                    throw ValidationException::withMessages([
                        'csv_file' => "CSV fiscal year {$csvYear} does not match {$annualBudget->fiscal_year_label}.",
                    ]);
                }
                if (! empty($data['fiscal_year_label']) && trim((string) $data['fiscal_year_label']) !== $annualBudget->fiscal_year_label) {
                    throw ValidationException::withMessages([
                        'csv_file' => "CSV fiscal year label must be {$annualBudget->fiscal_year_label}.",
                    ]);
                }
                if (! empty($data['fiscal_start_date']) && Carbon::parse($data['fiscal_start_date'])->toDateString() !== $annualBudget->fiscalStart()->toDateString()) {
                    throw ValidationException::withMessages([
                        'csv_file' => "CSV fiscal start date must be {$annualBudget->fiscalStart()->toDateString()}.",
                    ]);
                }
                if (! empty($data['fiscal_end_date']) && Carbon::parse($data['fiscal_end_date'])->toDateString() !== $annualBudget->fiscalEnd()->toDateString()) {
                    throw ValidationException::withMessages([
                        'csv_file' => "CSV fiscal end date must be {$annualBudget->fiscalEnd()->toDateString()}.",
                    ]);
                }

                $allocationMonth = isset($data['allocation_month']) && trim((string) $data['allocation_month']) !== ''
                    ? Carbon::parse($data['allocation_month'])->startOfMonth()
                    : $annualBudget->allocationMonthForNumber($this->parseMonthValue($data['month'] ?? 1));
                if (! $allocationMonth || ! $annualBudget->containsDate($allocationMonth)) {
                    throw ValidationException::withMessages([
                        'csv_file' => "Each allocation month must fall within {$annualBudget->fiscal_year_label}.",
                    ]);
                }

                $category = $this->resolveOrCreateCategory($data['budget_category'] ?? $data['category'] ?? null);
                $department = $this->resolveOrCreateDepartment($data['responsibility_center'] ?? $data['department'] ?? null);
                $account = $this->resolveOrCreateAccountTitle($data, $category, $department);

                $itemData = [
                    'budget_id' => $annualBudget->id,
                    'category_id' => $category->id,
                    'particular_id' => $account->id,
                    'month' => $allocationMonth->month,
                    'allocation_month' => $allocationMonth->toDateString(),
                    'appropriation' => $this->parseMoney($data['appropriation'] ?? 0),
                ];

                $existing = $annualBudget->items()
                    ->where('particular_id', $account->id)
                    ->whereDate('allocation_month', $allocationMonth->toDateString())
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'csv_file' => "Duplicate budget row rejected for {$allocationMonth->format('F Y')}, {$category->name}, {$department->name}, {$account->particular}.",
                    ]);
                }

                $item = $annualBudget->items()->create(array_merge($itemData, ['expenditure' => 0]));

                if ($item->wasRecentlyCreated) {
                    $rowsCreated++;
                } else {
                    $rowsUpdated++;
                }

                $this->reallocateBudgetItemIncome($item, (float) $itemData['appropriation']);
            }
        });

        if (($rowsCreated + $rowsUpdated) === 0) {
            return back()->withErrors([
                'csv_file' => 'No budget rows were imported. Please check the CSV headers, month values, and prerequisite data.',
            ]);
        }

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', "CSV imported successfully. Created {$rowsCreated} row(s), updated {$rowsUpdated} row(s). Missing categories/account titles were created automatically.");
    }

    public function index()
    {
        $budgets = AnnualBudget::with(['items.budget:id,year,start_date,end_date', 'items.category', 'items.particular.department'])
            ->latest('start_date')
            ->get();

        // Ensure ref_no is generated for existing annual budgets if null
        foreach ($budgets as $b) {
            if (! $b->ref_no) {
                $b->update(['ref_no' => sprintf('AB-%d-%04d', $b->year, $b->id)]);
            }
            foreach ($b->items as $item) {
                if (! $item->ref_no) {
                    $item->update(['ref_no' => sprintf('MB-%d-%02d-%04d', $b->year, $item->month ?: 1, $item->id)]);
                }
            }
        }
        $budgets->each(fn ($budget) => BudgetItem::hydrateDerivedTotals($budget->items));

        return Inertia::render('AnnualBudgets/Index', [
            'budgets' => $budgets,
            'availableYears' => AnnualBudget::distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    public function show(AnnualBudget $annualBudget)
    {
        if (! $annualBudget->ref_no) {
            $annualBudget->update(['ref_no' => sprintf('AB-%d-%04d', $annualBudget->year, $annualBudget->id)]);
        }

        $budget = $annualBudget->load(['items.budget:id,year,start_date,end_date', 'items.category', 'items.particular.department']);
        foreach ($budget->items as $item) {
            if (! $item->ref_no) {
                $item->update(['ref_no' => sprintf('MB-%d-%02d-%04d', $annualBudget->year, $item->month ?: 1, $item->id)]);
            }
        }
        BudgetItem::hydrateDerivedTotals($budget->items);

        return Inertia::render('AnnualBudgets/Show', [
            'budget' => $budget,
            'categories' => BudgetCategory::all(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
            'accountTitles' => BudgetParticular::with('category', 'department')->get(),
            'availableYears' => AnnualBudget::distinct()->orderByDesc('year')->pluck('year'),
            'allBudgets' => AnnualBudget::select('id', 'year', 'start_date', 'end_date', 'ref_no', 'semester')->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $validated = $this->validateFiscalPeriod($validated);
        $this->ensureIncomeExistsForPeriod($validated['start_date'], $validated['end_date']);

        $annualBudget = AnnualBudget::create($validated);
        AuditTrail::log($annualBudget, 'created', auth()->user(), "Created {$annualBudget->fiscal_year_label} ({$annualBudget->period_label})");

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $annualBudget->id, 'resource' => 'budget', 'record' => $annualBudget->fresh()], 201);
        }

        return redirect()->route('annual-budgets.index')->with('success', 'Annual Budget created with reference number '.$annualBudget->ref_no);
    }

    public function updatePeriod(Request $request, AnnualBudget $annualBudget)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'confirm_cross_calendar_remap' => 'nullable|boolean',
        ]);
        $confirmedRemap = (bool) ($validated['confirm_cross_calendar_remap'] ?? false);
        unset($validated['confirm_cross_calendar_remap']);
        $validated = $this->validateFiscalPeriod($validated, $annualBudget);
        $this->ensureIncomeExistsForPeriod($validated['start_date'], $validated['end_date']);

        $newStart = Carbon::parse($validated['start_date']);
        $newEnd = Carbon::parse($validated['end_date']);
        $isCrossCalendarConversion = $annualBudget->fiscalStart()->year === $annualBudget->fiscalEnd()->year
            && $newStart->year !== $newEnd->year;
        $ambiguousItemCount = $isCrossCalendarConversion
            ? $annualBudget->items()->where('month', '<', $newStart->month)->count()
            : 0;
        if ($ambiguousItemCount > 0 && ! $confirmedRemap) {
            throw ValidationException::withMessages([
                'confirm_cross_calendar_remap' => "{$ambiguousItemCount} existing January-July allocation(s) will move to the ending calendar year. Review and confirm this controlled remap.",
            ]);
        }

        $outsideExpenses = Expense::query()
            ->whereHas('budgetItem', fn ($query) => $query->where('budget_id', $annualBudget->id))
            ->whereNotBetween('date_encoded', [$validated['start_date'], $validated['end_date']])
            ->exists();

        if ($outsideExpenses) {
            throw ValidationException::withMessages([
                'start_date' => 'The period cannot be changed because a linked expense would fall outside the new fiscal year.',
            ]);
        }

        $outsideDisbursements = Disbursement::query()
            ->whereHas('expense.budgetItem', fn ($query) => $query->where('budget_id', $annualBudget->id))
            ->whereNotBetween('date_encoded', [$validated['start_date'], $validated['end_date']])
            ->exists();

        if ($outsideDisbursements) {
            throw ValidationException::withMessages([
                'end_date' => 'The period cannot be changed because a linked disbursement would fall outside the new fiscal year.',
            ]);
        }

        DB::transaction(function () use ($annualBudget, $validated) {
            $annualBudget->update($validated);

            foreach ($annualBudget->items()->get() as $item) {
                $allocationMonth = $annualBudget->allocationMonthForNumber((int) $item->month);
                if (! $allocationMonth) {
                    throw ValidationException::withMessages([
                        'start_date' => "Existing allocation {$item->ref_no} cannot be mapped into the selected period.",
                    ]);
                }
                $item->update(['allocation_month' => $allocationMonth->toDateString()]);
            }

            IncomeAllocation::where('annual_budget_id', $annualBudget->id)->delete();
            foreach ($annualBudget->items()->get() as $item) {
                $this->allocateIncomeToBudget($annualBudget, $item, (float) $item->appropriation);
            }
        });

        AuditTrail::log($annualBudget, 'modified', auth()->user(), "Updated fiscal period to {$annualBudget->fresh()->period_label}");

        return back()->with('success', 'Fiscal period updated successfully.');
    }

    public function storeItem(Request $request, AnnualBudget $annualBudget)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'allocation_month' => 'nullable|date|required_without:month',
            'month' => 'nullable|integer|between:1,12|required_without:allocation_month',
            'appropriation' => 'required|numeric|min:0',
        ]);

        $particular = BudgetParticular::find($validated['particular_id']);
        if (! $particular || (int) $particular->category_id !== (int) $validated['category_id'] || (int) $particular->department_id !== (int) $validated['department_id']) {
            throw ValidationException::withMessages([
                'particular_id' => 'Selected account title must belong to the chosen category and responsibility center.',
            ]);
        }

        $allocationMonth = ! empty($validated['allocation_month'])
            ? Carbon::parse($validated['allocation_month'])->startOfMonth()
            : $annualBudget->allocationMonthForNumber((int) $validated['month']);
        $errorField = ! empty($validated['allocation_month']) ? 'allocation_month' : 'month';
        if (! $annualBudget->containsDate($allocationMonth)) {
            throw ValidationException::withMessages([
                $errorField => "Choose a month within {$annualBudget->fiscal_year_label}.",
            ]);
        }

        $this->ensureIncomeExistsForPeriod(
            $annualBudget->fiscalStart()->toDateString(),
            $annualBudget->fiscalEnd()->toDateString()
        );

        $validated['allocation_month'] = $allocationMonth->toDateString();
        $validated['month'] = $allocationMonth->month;
        unset($validated['department_id']);

        $item = DB::transaction(function () use ($annualBudget, $validated) {
            $item = $annualBudget->items()->create(array_merge($validated, [
                'expenditure' => 0,
            ]));
            $this->allocateIncomeToBudget($annualBudget, $item, (float) $validated['appropriation']);

            return $item;
        });

        AuditTrail::log($item, 'created', auth()->user(), "Added Monthly Budget Allocation item {$item->ref_no}");

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $item->id, 'resource' => 'budget', 'record' => $item->fresh()], 201);
        }

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Monthly Budget Allocation added.');
    }

    public function updateItem(Request $request, AnnualBudget $annualBudget, BudgetItem $item)
    {
        abort_unless((int) $item->budget_id === (int) $annualBudget->id, 404);

        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'allocation_month' => 'nullable|date|required_without:month',
            'month' => 'nullable|integer|between:1,12|required_without:allocation_month',
            'appropriation' => 'required|numeric|min:0',
        ]);

        $particular = BudgetParticular::find($validated['particular_id']);
        if (! $particular || (int) $particular->category_id !== (int) $validated['category_id'] || (int) $particular->department_id !== (int) $validated['department_id']) {
            throw ValidationException::withMessages([
                'particular_id' => 'Selected account title must belong to the chosen category and responsibility center.',
            ]);
        }

        $allocationMonth = ! empty($validated['allocation_month'])
            ? Carbon::parse($validated['allocation_month'])->startOfMonth()
            : $annualBudget->allocationMonthForNumber((int) $validated['month']);
        $errorField = ! empty($validated['allocation_month']) ? 'allocation_month' : 'month';
        if (! $annualBudget->containsDate($allocationMonth)) {
            throw ValidationException::withMessages([
                $errorField => "Choose a month within {$annualBudget->fiscal_year_label}.",
            ]);
        }
        $month = $allocationMonth->month;
        $duplicateExists = $annualBudget->items()
            ->where('particular_id', $validated['particular_id'])
            ->whereDate('allocation_month', $allocationMonth->toDateString())
            ->whereKeyNot($item->id)
            ->exists();

        if ($duplicateExists) {
            $monthName = date('F', mktime(0, 0, 0, $month, 1));
            $periodText = $errorField === 'month'
                ? "{$monthName} {$annualBudget->fiscal_year_label}"
                : $allocationMonth->format('F Y');

            throw ValidationException::withMessages([
                $errorField => "An allocation already exists for {$particular->particular} in {$periodText}. Edit the existing row or choose another month.",
            ]);
        }

        $this->ensureIncomeExistsForPeriod(
            $annualBudget->fiscalStart()->toDateString(),
            $annualBudget->fiscalEnd()->toDateString()
        );

        $validated['month'] = $month;
        $validated['allocation_month'] = $allocationMonth->toDateString();
        unset($validated['department_id']);

        DB::transaction(function () use ($item, $validated) {
            $item->update($validated);
            $this->reallocateBudgetItemIncome($item, (float) $validated['appropriation']);
        });

        AuditTrail::log($item, 'modified', auth()->user(), "Updated Monthly Budget Allocation item {$item->ref_no}");

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $item->id, 'resource' => 'budget', 'record' => $item->fresh()]);
        }

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Monthly Budget Allocation updated.');
    }

    public function destroyItem(AnnualBudget $annualBudget, BudgetItem $item)
    {
        IncomeAllocation::where('budget_item_id', $item->id)->delete();
        AuditTrail::log($item, 'deleted', auth()->user(), "Deleted Monthly Budget Allocation item {$item->ref_no}");
        $item->delete();

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Monthly Budget Allocation deleted.');
    }

    public function destroy(AnnualBudget $annualBudget)
    {
        DB::transaction(function () use ($annualBudget) {
            $items = $annualBudget->items()->get();
            $itemIds = $items->pluck('id');
            if ($itemIds->isNotEmpty()) {
                IncomeAllocation::whereIn('budget_item_id', $itemIds)->delete();
                AuditTrail::where('auditable_type', BudgetItem::class)
                    ->whereIn('auditable_id', $itemIds)
                    ->delete();

                $expenseIds = Expense::query()
                    ->whereIn('budget_item_id', $itemIds)
                    ->pluck('id');

                if ($expenseIds->isNotEmpty()) {
                    $disbursementIds = Disbursement::whereIn('expense_id', $expenseIds)->pluck('id');
                    if ($disbursementIds->isNotEmpty()) {
                        AuditTrail::where('auditable_type', Disbursement::class)
                            ->whereIn('auditable_id', $disbursementIds)
                            ->delete();
                        Disbursement::whereIn('id', $disbursementIds)->delete();
                    }
                    AuditTrail::where('auditable_type', Expense::class)
                        ->whereIn('auditable_id', $expenseIds)
                        ->delete();
                    Expense::whereIn('id', $expenseIds)->delete();
                }

                $annualBudget->items()->delete();
            }

            IncomeAllocation::where('annual_budget_id', $annualBudget->id)->delete();
            AuditTrail::where('auditable_type', AnnualBudget::class)
                ->where('auditable_id', $annualBudget->id)
                ->delete();
            AuditTrail::log($annualBudget, 'deleted', auth()->user(), "Deleted Annual Budget {$annualBudget->ref_no}");
            $annualBudget->delete();
        });

        return redirect()->route('annual-budgets.index')->with('success', 'Annual Budget deleted.');
    }
}
