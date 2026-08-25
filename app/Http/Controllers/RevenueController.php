<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $availableYears = collect()
            ->merge(AnnualBudget::query()->distinct()->pluck('year'))
            ->merge(Income::query()->selectRaw('YEAR(date_encoded) as year')->distinct()->pluck('year'))
            ->merge(Expense::query()->selectRaw('YEAR(date_encoded) as year')->distinct()->pluck('year'))
            ->merge(Disbursement::query()->selectRaw('YEAR(date_encoded) as year')->distinct()->pluck('year'))
            ->merge([date('Y')])
            ->filter(fn ($year) => !is_null($year) && (int) $year > 0)
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $selectedYear = (int) ($request->query('year') ?: ($availableYears[0] ?? date('Y')));
        $selectedMonth = $request->query('month') ? (int) $request->query('month') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!in_array($selectedYear, $availableYears, true)) {
            $selectedYear = (int) ($availableYears[0] ?? date('Y'));
        }

        $budgetItemsQuery = BudgetItem::query()
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));
        if ($selectedMonth) {
            $budgetItemsQuery->where('month', $selectedMonth);
        }
        $budgetItems = $budgetItemsQuery->with(['budget', 'category', 'particular.department'])->get();
        BudgetItem::hydrateDerivedTotals($budgetItems);

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

        $totalIncome = (float) Income::query()
            ->whereYear('date_encoded', $selectedYear)
            ->sum('amount');
        $totalAppropriation = (float) BudgetItem::query()
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear))
            ->sum('appropriation');
        // IAEO should reflect actual paid-out money, so use posted disbursements
        // as the source of truth instead of workflow states like pending/approved.
        $postedDisbursementQuery = Disbursement::query()
            ->where('status', 'posted')
            ->whereYear('date_encoded', $selectedYear);
        if ($startDate && $endDate) {
            $postedDisbursementQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $postedDisbursementQuery->whereMonth('date_encoded', $selectedMonth);
        }
        $totalExpense = (float) $postedDisbursementQuery->sum('amount');
        $remainingAppropriation = $totalAppropriation - $totalExpense;
        $remainingIncome = $totalIncome - $totalAppropriation;
        $remainingIncomeAfterExpense = $totalIncome - $totalExpense;

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyIncomeTotals = Income::query()
            ->whereYear('date_encoded', $selectedYear)
            ->selectRaw('MONTH(date_encoded) as month, SUM(amount) as total')
            ->groupByRaw('MONTH(date_encoded)')
            ->pluck('total', 'month');
        $monthlyAppropriationTotals = BudgetItem::query()
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear))
            ->selectRaw('month, SUM(appropriation) as total')
            ->groupBy('month')
            ->pluck('total', 'month');
        $monthlyExpenseTotals = Disbursement::query()
            ->where('status', 'posted')
            ->whereYear('date_encoded', $selectedYear)
            ->selectRaw('MONTH(date_encoded) as month, SUM(amount) as total')
            ->groupByRaw('MONTH(date_encoded)')
            ->pluck('total', 'month');

        $monthlyIncome = collect(range(1, 12))->map(fn ($m) => [
                'month' => $monthNames[$m - 1],
                'month_num' => $m,
                'amount' => (float) ($monthlyIncomeTotals[$m] ?? 0),
            ])->all();
        $monthlyAppropriation = collect(range(1, 12))->map(fn ($m) => [
                'month' => $monthNames[$m - 1],
                'month_num' => $m,
                'amount' => (float) ($monthlyAppropriationTotals[$m] ?? 0),
            ])->all();
        $monthlyExpense = collect(range(1, 12))->map(fn ($m) => [
                'month' => $monthNames[$m - 1],
                'month_num' => $m,
                'amount' => (float) ($monthlyExpenseTotals[$m] ?? 0),
            ])->all();

        $compareYears = collect($availableYears)->take(4)->sort()->values()->all();
        $yearIncomeTotals = Income::query()
            ->whereIn(DB::raw('YEAR(date_encoded)'), $compareYears)
            ->selectRaw('YEAR(date_encoded) as year, SUM(amount) as total')
            ->groupByRaw('YEAR(date_encoded)')
            ->pluck('total', 'year');
        $yearAppropriationTotals = BudgetItem::query()
            ->join('annual_budgets', 'budget_items.budget_id', '=', 'annual_budgets.id')
            ->whereIn('annual_budgets.year', $compareYears)
            ->selectRaw('annual_budgets.year, SUM(budget_items.appropriation) as total')
            ->groupBy('annual_budgets.year')
            ->pluck('total', 'year');
        $yearExpenseTotals = Disbursement::query()
            ->where('status', 'posted')
            ->whereIn(DB::raw('YEAR(date_encoded)'), $compareYears)
            ->selectRaw('YEAR(date_encoded) as year, SUM(amount) as total')
            ->groupByRaw('YEAR(date_encoded)')
            ->pluck('total', 'year');

        $multiYearComparison = collect($compareYears)->map(function ($year) use ($yearIncomeTotals, $yearAppropriationTotals, $yearExpenseTotals) {
            $yearIncome = (float) ($yearIncomeTotals[$year] ?? 0);
            $yearAppropriation = (float) ($yearAppropriationTotals[$year] ?? 0);
            $yearExpense = (float) ($yearExpenseTotals[$year] ?? 0);

            return [
                'year' => (int) $year,
                'income' => $yearIncome,
                'appropriation' => $yearAppropriation,
                'expense' => $yearExpense,
                'remainingIncome' => $yearIncome - $yearAppropriation,
                'remainingAppropriation' => $yearAppropriation - $yearExpense,
            ];
        })->all();

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
                'remainingIncome' => $remainingIncome,
                'remainingIncomeAfterExpense' => $remainingIncomeAfterExpense,
                'utilizationRate' => $totalAppropriation > 0 ? round(($totalExpense / $totalAppropriation) * 100, 2) : 0,
            ],
            'monthlyIncome' => $monthlyIncome,
            'monthlyRevenue' => $monthlyAppropriation,
            'monthlyExpense' => $monthlyExpense,
            'multiYearComparison' => $multiYearComparison,
            'budgetItems' => $budgetItems,
            'incomeRecords' => $incomeRecords,
        ]);
    }
}
