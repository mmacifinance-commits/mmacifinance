<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = (int) ($request->query('year') ?: date('Y'));
        $selectedMonth = $request->query('month') ? (int) $request->query('month') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $availableYears = AnnualBudget::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->concat([(int) date('Y')])
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $budgetItemsQuery = BudgetItem::query()
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));
        if ($selectedMonth) {
            $budgetItemsQuery->where('month', $selectedMonth);
        }
        $budgetItems = $budgetItemsQuery->with(['budget', 'category', 'particular.department'])->get();

        $incomeQuery = Income::query()->whereYear('date_encoded', $selectedYear);
        if ($startDate && $endDate) {
            $incomeQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $incomeQuery->whereMonth('date_encoded', $selectedMonth);
        }
        $incomeRecords = $incomeQuery->get();

        $expenseQuery = Expense::query()->whereYear('date_encoded', $selectedYear);
        if ($startDate && $endDate) {
            $expenseQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $expenseQuery->whereMonth('date_encoded', $selectedMonth);
        }
        $expenseRecords = $expenseQuery->get();

        $totalIncome = (float) $incomeRecords->sum('amount');
        $totalAppropriation = (float) $budgetItems->sum('appropriation');
        $totalExpense = (float) $expenseRecords->sum(fn ($expense) => (float) ($expense->paid ?: $expense->amount));
        $remainingAppropriation = $totalAppropriation - $totalExpense;
        $incomeLessAppropriation = $totalIncome - $totalAppropriation;

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyIncome = [];
        $monthlyAppropriation = [];
        $monthlyExpense = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyIncome[] = [
                'month' => $monthNames[$m - 1],
                'month_num' => $m,
                'amount' => (float) Income::whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $m)->sum('amount'),
            ];
            $monthlyAppropriation[] = [
                'month' => $monthNames[$m - 1],
                'month_num' => $m,
                'amount' => (float) BudgetItem::whereHas('budget', fn ($q) => $q->where('year', $selectedYear))->where('month', $m)->sum('appropriation'),
            ];
            $monthlyExpense[] = [
                'month' => $monthNames[$m - 1],
                'month_num' => $m,
                'amount' => (float) Expense::whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $m)->sum('paid'),
            ];
        }

        return Inertia::render('Revenue/Index', [
            'availableYears' => $availableYears,
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'stats' => [
                'totalIncome' => $totalIncome,
                'totalRevenue' => $totalAppropriation,
                'totalExpense' => $totalExpense,
                'balance' => $remainingAppropriation,
                'incomeLessAppropriation' => $incomeLessAppropriation,
                'utilizationRate' => $totalAppropriation > 0 ? round(($totalExpense / $totalAppropriation) * 100, 2) : 0,
            ],
            'monthlyIncome' => $monthlyIncome,
            'monthlyRevenue' => $monthlyAppropriation,
            'monthlyExpense' => $monthlyExpense,
            'budgetItems' => $budgetItems,
            'incomeRecords' => $incomeRecords,
        ]);
    }
}
