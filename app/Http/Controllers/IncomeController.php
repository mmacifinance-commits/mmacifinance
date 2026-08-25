<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\Expense;
use App\Models\BudgetItem;
use App\Models\Disbursement;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $availableYears = Income::query()
            ->selectRaw('YEAR(date_encoded) as year')
            ->distinct()
            ->pluck('year')
            ->concat([(int) date('Y')])
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $selectedYear = (int) ($request->query('year') ?: ($availableYears[0] ?? date('Y')));
        $selectedMonth = $request->query('month') ? (int) $request->query('month') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $search = trim((string) $request->query('search', ''));

        $query = Income::query();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('income_no', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($startDate && $endDate) {
            $query->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $query->whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $selectedMonth);
        } else {
            $query->whereYear('date_encoded', $selectedYear);
        }

        $recordCount = (clone $query)->count();
        $incomeRecords = $query->latest('date_encoded')->paginate(25)->withQueryString();
        $incomeTotalQuery = Income::query()->whereYear('date_encoded', $selectedYear);
        $expenseTotalQuery = Disbursement::query()
            ->where('status', 'posted')
            ->whereYear('date_encoded', $selectedYear);
        $appropriationTotalQuery = BudgetItem::query()
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));

        if ($startDate && $endDate) {
            $incomeTotalQuery->whereBetween('date_encoded', [$startDate, $endDate]);
            $expenseTotalQuery->whereBetween('date_encoded', [$startDate, $endDate]);
            $appropriationTotalQuery->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));
        } elseif ($selectedMonth) {
            $incomeTotalQuery->whereMonth('date_encoded', $selectedMonth);
            $expenseTotalQuery->whereMonth('date_encoded', $selectedMonth);
            $appropriationTotalQuery->where('month', $selectedMonth);
        }

        $totalRevenue = (float) $incomeTotalQuery->sum('amount');
        $totalAppropriation = (float) $appropriationTotalQuery->sum('appropriation');
        $totalExpense = (float) $expenseTotalQuery->sum('amount');
        $remainingIncome = $totalRevenue - $totalAppropriation;
        $remainingIncomeAfterExpense = $totalRevenue - $totalExpense;

        return Inertia::render('Income/Index', [
            'incomeRecords' => $incomeRecords,
            'availableYears' => $availableYears,
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedMonth,
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
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

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
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

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
        $fileName = 'income-export-' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['income_no', 'source', 'description', 'amount', 'date_encoded', 'notes']);

            Income::query()
                ->orderBy('date_encoded')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $income) {
                        fputcsv($handle, [
                            $income->income_no,
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
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV file is empty.']);
        }

        $header = array_map(fn ($value) => trim((string) $value), $header);
        $required = ['source', 'description', 'amount', 'date_encoded', 'notes'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
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

            $isNew = !$income->exists;
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
