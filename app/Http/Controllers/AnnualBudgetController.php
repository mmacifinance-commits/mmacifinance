<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Income;
use App\Models\IncomeAllocation;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AnnualBudgetController extends Controller
{
    protected const FULL_YEAR_SEMESTER = 'Full Year (Jan-Dec)';

    protected function ensureIncomeExistsForYear(int $year): void
    {
        if (!Income::whereYear('date_encoded', $year)->exists()) {
            throw ValidationException::withMessages([
                'year' => "You must create at least one income record for {$year} before creating appropriation.",
            ]);
        }
    }

    protected function allocatedIncomeTotalForYear(int $year): float
    {
        return (float) IncomeAllocation::query()
            ->whereHas('annualBudget', fn ($q) => $q->where('year', $year))
            ->sum('amount');
    }

    protected function incomePoolForYear(int $year): array
    {
        return Income::query()
            ->whereYear('date_encoded', $year)
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

        $year = (int) $annualBudget->year;
        $availableIncome = $this->incomePoolForYear($year);
        $allocatedForYear = $this->allocatedIncomeTotalForYear($year);
        $yearlyIncomeTotal = (float) Income::whereYear('date_encoded', $year)->sum('amount');
        $availableBalance = round($yearlyIncomeTotal - $allocatedForYear, 2);

        if ($availableBalance < $remainingToAllocate) {
            throw ValidationException::withMessages([
                'appropriation' => "Not enough remaining income for {$year}. Available income balance is " . number_format($availableBalance, 2),
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

    protected function hydrateBudgetItemTotals(AnnualBudget $budget): void
    {
        $budget->items->each(function (BudgetItem $item) {
            $expenditure = $item->postedExpenditureTotal();
            $item->setAttribute('expenditure', $expenditure);
            $item->setAttribute('balance', round((float) $item->appropriation - $expenditure, 2));
            $appropriation = (float) $item->appropriation;
            $item->setAttribute('utilization_rate', $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 2) : 0.0);
        });
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

    protected function uniqueCode(string $base, string $prefix, int $maxLength = 20): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $base));
        $clean = $clean !== '' ? $clean : $prefix;
        $clean = substr($clean, 0, max(1, $maxLength - strlen($prefix) - 4));
        $candidate = $prefix . $clean;
        $i = 1;

        while (\App\Models\Department::where('code', $candidate)->exists() || \App\Models\BudgetParticular::where('account_code', $candidate)->exists()) {
            $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $candidate = substr($prefix . $clean, 0, max(1, $maxLength - strlen($suffix))) . $suffix;
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
            $lookup = \App\Models\BudgetParticular::where('account_code', $accountCode)->first();
        }
        if (!$lookup && $accountName !== '') {
            $lookup = \App\Models\BudgetParticular::whereRaw('LOWER(particular) = ?', [Str::lower($particular ?: $accountName)])->first();
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
                'semester',
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
                    $budget->semester,
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

        $this->ensureIncomeExistsForYear((int) $annualBudget->year);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return back()->with('error', 'Unable to read CSV file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return back()->with('error', 'CSV file is empty.');
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $required = ['month', 'budget_category', 'account_title', 'appropriation'];
        foreach ($required as $column) {
            if (!in_array($column, $headers, true)) {
                fclose($handle);
                return back()->with('error', 'CSV is missing required column: ' . $column);
            }
        }

        $rowsCreated = 0;
        $rowsUpdated = 0;

        DB::transaction(function () use ($handle, $headers, $annualBudget, &$rowsCreated, &$rowsUpdated) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $data = [];
                foreach ($headers as $index => $header) {
                    $data[$header] = $row[$index] ?? null;
                }

                $csvYear = isset($data['fiscal_year']) && $data['fiscal_year'] !== '' ? (int) $data['fiscal_year'] : (int) $annualBudget->year;
                if ($csvYear !== (int) $annualBudget->year) {
                    throw ValidationException::withMessages([
                        'csv_file' => "CSV fiscal year {$csvYear} does not match Annual Budget year {$annualBudget->year}.",
                    ]);
                }

                $month = (int) ($data['month'] ?? 1);
                if ($month < 1 || $month > 12) {
                    throw ValidationException::withMessages([
                        'csv_file' => 'Each row must have a month between 1 and 12.',
                    ]);
                }

                $category = $this->resolveOrCreateCategory($data['budget_category'] ?? $data['category'] ?? null);
                $department = $this->resolveOrCreateDepartment($data['responsibility_center'] ?? $data['department'] ?? null);
                $account = $this->resolveOrCreateAccountTitle($data, $category, $department);

                $itemData = [
                    'budget_id' => $annualBudget->id,
                    'category_id' => $category->id,
                    'particular_id' => $account->id,
                    'month' => $month,
                    'appropriation' => $this->parseMoney($data['appropriation'] ?? 0),
                ];

                $existing = $annualBudget->items()
                    ->where('particular_id', $account->id)
                    ->where('month', $month)
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'csv_file' => "Duplicate budget row rejected for {$annualBudget->year}, month {$month}, {$category->name}, {$department->name}, {$account->particular}.",
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

            fclose($handle);
        });

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', "CSV imported successfully. Created {$rowsCreated} row(s), updated {$rowsUpdated} row(s). Missing categories/account titles were created automatically.");
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
            $this->hydrateBudgetItemTotals($b);
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
        $this->hydrateBudgetItemTotals($budget);

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
        $validated['semester'] = $this->normalizeSemester($validated['semester'] ?? null);

        $annualBudget = AnnualBudget::create($validated);
        AuditTrail::log($annualBudget, 'created', auth()->user(), "Created Annual Budget for year {$annualBudget->year}");

        return redirect()->route('annual-budgets.index')->with('success', 'Annual Budget created with reference number ' . $annualBudget->ref_no);
    }

    public function storeItem(Request $request, AnnualBudget $annualBudget)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'month' => 'nullable|integer|min:1|max:12',
            'appropriation' => 'required|numeric|min:0',
        ]);

        $particular = BudgetParticular::find($validated['particular_id']);
        if (!$particular || (int) $particular->category_id !== (int) $validated['category_id'] || (int) $particular->department_id !== (int) $validated['department_id']) {
            throw ValidationException::withMessages([
                'particular_id' => 'Selected account title must belong to the chosen category and responsibility center.',
            ]);
        }

        $this->ensureIncomeExistsForYear((int) $annualBudget->year);

        $validated['month'] = $validated['month'] ?: 1;
        unset($validated['department_id']);

        $item = DB::transaction(function () use ($annualBudget, $validated) {
            $item = $annualBudget->items()->create(array_merge($validated, [
                'expenditure' => 0,
            ]));
            $this->allocateIncomeToBudget($annualBudget, $item, (float) $validated['appropriation']);
            return $item;
        });

        AuditTrail::log($item, 'created', auth()->user(), "Added Monthly Budget Allocation item {$item->ref_no}");

        return redirect()->route('annual-budgets.show', $annualBudget)->with('success', 'Monthly Budget Allocation added.');
    }

    protected function normalizeSemester(?string $semester): string
    {
        $semester = trim((string) $semester);
        $lower = strtolower($semester);

        if ($semester === '' || $lower === 'full year' || $lower === 'full year (jan-dec)' || $lower === 'full year (jan - dec)' || $lower === 'full year (jan–dec)' || $lower === 'full year (jan – dec)') {
            return self::FULL_YEAR_SEMESTER;
        }

        return $semester;
    }

    public function updateItem(Request $request, AnnualBudget $annualBudget, BudgetItem $item)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'particular_id' => 'required|exists:budget_particulars,id',
            'month' => 'nullable|integer|min:1|max:12',
            'appropriation' => 'required|numeric|min:0',
        ]);

        $particular = BudgetParticular::find($validated['particular_id']);
        if (!$particular || (int) $particular->category_id !== (int) $validated['category_id'] || (int) $particular->department_id !== (int) $validated['department_id']) {
            throw ValidationException::withMessages([
                'particular_id' => 'Selected account title must belong to the chosen category and responsibility center.',
            ]);
        }

        $this->ensureIncomeExistsForYear((int) $annualBudget->year);

        $validated['month'] = $validated['month'] ?: $item->month ?: 1;
        unset($validated['department_id']);

        DB::transaction(function () use ($item, $validated) {
            $item->update($validated);
            $this->reallocateBudgetItemIncome($item, (float) $validated['appropriation']);
        });

        AuditTrail::log($item, 'modified', auth()->user(), "Updated Monthly Budget Allocation item {$item->ref_no}");

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
            $year = (int) $annualBudget->year;

            if ($itemIds->isNotEmpty()) {
                IncomeAllocation::whereIn('budget_item_id', $itemIds)->delete();
                AuditTrail::where('auditable_type', BudgetItem::class)
                    ->whereIn('auditable_id', $itemIds)
                    ->delete();

                $expenseIds = Expense::query()
                    ->whereYear('date_encoded', $year)
                    ->where(function ($query) use ($items) {
                        foreach ($items as $item) {
                            $query->orWhere(function ($subQuery) use ($item) {
                                $subQuery->where('category_id', $item->category_id)
                                    ->where('particular_id', $item->particular_id);
                            });
                        }
                    })
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
