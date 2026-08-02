<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use Illuminate\Http\Request;
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

        $incomeRecords = $query->latest('date_encoded')->get();
        $totalRevenue = (float) Income::query()
            ->whereYear('date_encoded', $selectedYear)
            ->sum('amount');

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
                'recordCount' => $incomeRecords->count(),
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

        Income::create($validated);

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

        return redirect()->back()->with('success', 'Income item updated successfully.');
    }

    public function destroy(Income $income)
    {
        $income->delete();

        return redirect()->back()->with('success', 'Income item deleted successfully.');
    }
}
