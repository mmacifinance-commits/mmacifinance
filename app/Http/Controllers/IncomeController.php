<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\AuditTrail;
use App\Models\Income;
use App\Services\BudgetUtilizationService;
use App\Services\FiscalPeriodLockService;
use App\Services\FiscalPeriodService;
use App\Support\SpreadsheetImportExport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class IncomeController extends Controller
{
    public function index(
        Request $request,
        BudgetUtilizationService $utilization,
        FiscalPeriodService $fiscalPeriods
    ) {
        $periods = $fiscalPeriods->all();
        $selectedPeriod = $fiscalPeriods->resolve(
            $request->integer('fiscal_period_id') ?: null,
            $request->integer('year') ?: null
        );
        $allocationMonth = $selectedPeriod
            ? $fiscalPeriods->allocationMonth(
                $selectedPeriod,
                $request->query('allocation_month'),
                $request->integer('month') ?: null
            )
            : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $search = trim((string) $request->query('search', ''));

        $incomeTotalQuery = Income::projected();
        if ($selectedPeriod) {
            $incomeTotalQuery->whereBetween('date_encoded', [
                $selectedPeriod->fiscalStart()->toDateString(),
                $selectedPeriod->fiscalEnd()->toDateString(),
            ]);
        }
        if ($startDate) {
            $incomeTotalQuery->whereDate('date_encoded', '>=', $startDate);
        }
        if ($endDate) {
            $incomeTotalQuery->whereDate('date_encoded', '<=', $endDate);
        }
        if (! $startDate && ! $endDate && $allocationMonth) {
            $monthStart = \Carbon\Carbon::parse($allocationMonth);
            $incomeTotalQuery->whereBetween('date_encoded', [
                $monthStart->toDateString(),
                $monthStart->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $query = clone $incomeTotalQuery;
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('income_no', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordCount = (clone $query)->count();
        $incomeRecords = $query->latest('date_encoded')->paginate(25)->withQueryString();

        $expenseTotalQuery = $selectedPeriod
            ? $utilization->queryForAnnualBudgetFilters(
                $selectedPeriod,
                $allocationMonth,
                $startDate,
                $endDate
            )
            : null;
        $appropriationTotalQuery = BudgetItem::query()
            ->when($selectedPeriod, fn ($q) => $q->where('budget_id', $selectedPeriod->id))
            ->when($allocationMonth, fn ($q) => $q->whereDate('allocation_month', $allocationMonth));

        $totalRevenue = (float) $incomeTotalQuery->sum('amount');
        $totalAppropriation = (float) $appropriationTotalQuery->sum('appropriation');
        $totalExpense = (float) ($expenseTotalQuery?->sum('amount') ?? 0);
        $remainingIncome = $totalRevenue - $totalAppropriation;
        $remainingIncomeAfterExpense = $totalRevenue - $totalExpense;

        return Inertia::render('Income/Index', [
            'incomeRecords' => $incomeRecords,
            'availableYears' => $periods->pluck('year')->all(),
            'fiscalPeriods' => $fiscalPeriods->options($periods),
            'filters' => [
                'year' => $selectedPeriod?->year,
                'fiscal_period_id' => $selectedPeriod?->id,
                'month' => $allocationMonth ? (int) date('n', strtotime($allocationMonth)) : null,
                'allocation_month' => $allocationMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
            ],
            'stats' => [
                'totalRevenue' => $totalRevenue,
                'recordCount' => $recordCount,
                'totalAppropriation' => $totalAppropriation,
                'totalExpense' => $totalExpense,
                'remainingIncome' => $remainingIncome,
                'remainingIncomeAfterExpense' => $remainingIncomeAfterExpense,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receipt_no' => 'prohibited',
            'receipt_type' => 'prohibited',
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        unset($validated['receipt_no'], $validated['receipt_type']);
        app(FiscalPeriodLockService::class)->ensureDateOpen($validated['date_encoded']);
        $validated['income_no'] = sprintf('INC-%s-%04d', date('Y'), Income::count() + 1);
        $validated['created_by_id'] = auth()->id();

        $income = Income::create($validated);
        AuditTrail::log($income, 'created', auth()->user(), 'Income record created.');

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $income->id, 'resource' => 'income', 'record' => $income->fresh()], 201);
        }

        return redirect()->back()->with('success', 'Income item created successfully.');
    }

    public function update(Request $request, Income $income)
    {
        $this->ensureProjected($income);
        $validated = $request->validate([
            'receipt_no' => 'prohibited',
            'receipt_type' => 'prohibited',
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        unset($validated['receipt_no'], $validated['receipt_type']);
        app(FiscalPeriodLockService::class)->ensureDateOpen($income->date_encoded);
        app(FiscalPeriodLockService::class)->ensureDateOpen($validated['date_encoded']);
        $income->update($validated);
        AuditTrail::log($income, 'modified', auth()->user(), 'Income record updated.', [
            'changes' => $validated,
        ]);

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $income->id, 'resource' => 'income', 'record' => $income->fresh()]);
        }

        return redirect()->back()->with('success', 'Income item updated successfully.');
    }

    public function destroy(Income $income, bool $bulk = false)
    {
        $this->ensureProjected($income);
        app(\App\Services\FinancialDeletionGuard::class)->check($income);
        app(FiscalPeriodLockService::class)->ensureDateOpen($income->date_encoded);
        AuditTrail::log($income, 'deleted', auth()->user(), 'Income record deleted.');
        $income->delete();

        return $bulk ? response()->noContent() : redirect()->back()->with('success', 'Income item deleted successfully.');
    }

    private function ensureProjected(Income $income): void
    {
        if (filled($income->receipt_no)) {
            throw ValidationException::withMessages(['income' => 'This record is a receipt. Manage it from the Receipts page. No changes were saved.']);
        }
    }

    public function exportCsv()
    {
        $fileName = 'income-export-'.now()->format('Y-m-d_His');
        $rows = [['income_no', 'source', 'description', 'amount', 'date_encoded', 'notes']];

        Income::projected()
            ->orderBy('date_encoded')
            ->orderBy('id')
            ->chunk(200, function ($rowsChunk) use (&$rows) {
                foreach ($rowsChunk as $income) {
                    $rows[] = [
                        $income->income_no,
                        $income->source,
                        $income->description,
                        $income->amount,
                        optional($income->date_encoded)->format('Y-m-d'),
                        $income->notes,
                    ];
                }
            });

        return SpreadsheetImportExport::downloadXlsx($fileName, $rows);
    }

    public function importCsv(Request $request)
    {
        $lock = app(FiscalPeriodLockService::class);
        $request->validate(SpreadsheetImportExport::validationRules('csv_file', true));

        try {
            [$header, $rows] = SpreadsheetImportExport::readRows($request->file('csv_file'));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['csv_file' => $exception->getMessage()]);
        }

        if (empty($header)) {
            return back()->withErrors(['csv_file' => 'CSV/Excel file is empty.']);
        }

        $required = ['source', 'description', 'amount', 'date_encoded', 'notes'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                return back()->withErrors(['csv_file' => "Missing required column: {$column}"]);
            }
        }

        $index = array_flip($header);
        foreach ($rows as $row) {
            if (filled($row[$index['receipt_no'] ?? -1] ?? null) || filled($row[$index['receipt_type'] ?? -1] ?? null)) {
                return back()->withErrors(['csv_file' => 'Income is projected income. Import records with receipt details from the Receipts page. No rows were saved.']);
            }
        }
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $source = trim((string) ($row[$index['source']] ?? ''));
            $description = trim((string) ($row[$index['description']] ?? ''));
            $amount = (float) ($row[$index['amount']] ?? 0);
            $dateEncoded = trim((string) ($row[$index['date_encoded']] ?? ''));
            $notes = trim((string) ($row[$index['notes']] ?? ''));

            if ($source === '' || $description === '' || $dateEncoded === '') {
                continue;
            }

            $lock->ensureDateOpen($dateEncoded, 'csv_file');

            $income = Income::projected()
                ->where('source', $source)
                ->where('description', $description)
                ->whereDate('date_encoded', $dateEncoded)
                ->first() ?? new Income;

            $isNew = ! $income->exists;
            $income->source = $source;
            $income->description = $description;
            $income->amount = $amount;
            $income->date_encoded = $dateEncoded;
            $income->notes = $notes !== '' ? $notes : null;
            if ($isNew) {
                $income->income_no = sprintf('INC-%s-%04d', date('Y'), Income::count() + 1);
                $income->created_by_id = auth()->id();
            }
            $income->save();
            AuditTrail::log($income, 'imported', auth()->user(), $isNew ? 'Income record created from CSV/Excel.' : 'Income record updated from CSV/Excel.');

            $isNew ? $created++ : $updated++;
        }

        return redirect()->back()->with('success', "Income CSV/Excel imported successfully. Created: {$created}, Updated: {$updated}");
    }
}
