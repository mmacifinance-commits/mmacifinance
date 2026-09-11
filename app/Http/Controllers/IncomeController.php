<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\Income;
use App\Services\BudgetUtilizationService;
use App\Services\FiscalPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
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

        $incomeTotalQuery = Income::query();
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
                    ->orWhere('receipt_no', 'like', "%{$search}%")
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
            'receipt_no' => 'nullable|string|max:100',
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $validated['receipt_no'] = filled($validated['receipt_no'] ?? null) ? trim($validated['receipt_no']) : null;
        $validated['income_no'] = sprintf('INC-%s-%04d', date('Y'), Income::count() + 1);
        $validated['created_by_id'] = auth()->id();

        $income = Income::create($validated);

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $income->id, 'resource' => 'income', 'record' => $income->fresh()], 201);
        }

        return redirect()->back()->with('success', 'Income item created successfully.');
    }

    public function update(Request $request, Income $income)
    {
        $validated = $request->validate([
            'receipt_no' => 'nullable|string|max:100',
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $validated['receipt_no'] = filled($validated['receipt_no'] ?? null) ? trim($validated['receipt_no']) : null;
        $income->update($validated);

        if ($request->header('X-Offline-Sync')) {
            return response()->json(['id' => $income->id, 'resource' => 'income', 'record' => $income->fresh()]);
        }

        return redirect()->back()->with('success', 'Income item updated successfully.');
    }

    public function destroy(Income $income)
    {
        $income->delete();

        return redirect()->back()->with('success', 'Income item deleted successfully.');
    }

    public function exportCsv()
    {
        $fileName = 'income-export-'.now()->format('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['income_no', 'receipt_no', 'source', 'description', 'amount', 'date_encoded', 'notes']);

            Income::query()
                ->orderBy('date_encoded')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $income) {
                        fputcsv($handle, [
                            $income->income_no,
                            $income->receipt_no,
                            $income->source,
                            $income->description,
                            $income->amount,
                            optional($income->date_encoded)->format('Y-m-d'),
                            $income->notes,
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
        if (! $header) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'CSV file is empty.']);
        }

        $header = array_map(fn ($value) => trim((string) $value), $header);
        $required = ['source', 'description', 'amount', 'date_encoded', 'notes'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);

                return back()->withErrors(['csv_file' => "Missing required column: {$column}"]);
            }
        }

        $index = array_flip($header);
        $created = 0;
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $source = trim((string) ($row[$index['source']] ?? ''));
            $receiptNo = isset($index['receipt_no']) ? trim((string) ($row[$index['receipt_no']] ?? '')) : '';
            $description = trim((string) ($row[$index['description']] ?? ''));
            $amount = (float) ($row[$index['amount']] ?? 0);
            $dateEncoded = trim((string) ($row[$index['date_encoded']] ?? ''));
            $notes = trim((string) ($row[$index['notes']] ?? ''));

            if ($source === '' || $description === '' || $dateEncoded === '') {
                continue;
            }

            $income = Income::firstOrNew([
                'source' => $source,
                'description' => $description,
                'date_encoded' => $dateEncoded,
            ]);

            $isNew = ! $income->exists;
            $income->receipt_no = $receiptNo !== '' ? $receiptNo : null;
            $income->amount = $amount;
            $income->notes = $notes !== '' ? $notes : null;
            if ($isNew) {
                $income->income_no = sprintf('INC-%s-%04d', date('Y'), Income::count() + 1);
                $income->created_by_id = auth()->id();
            }
            $income->save();

            $isNew ? $created++ : $updated++;
        }

        fclose($handle);

        return redirect()->back()->with('success', "Income CSV imported successfully. Created: {$created}, Updated: {$updated}");
    }
}
