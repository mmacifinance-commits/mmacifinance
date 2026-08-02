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
                'amount' => (float) BudgetItem::query()
                    ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear))
                    ->where('month', $m)
                    ->sum('appropriation'),
            ];
            $monthlyExpense[] = [
                'month' => $monthNames[$m - 1],
                'month_num' => $m,
                'amount' => (float) Disbursement::query()
                    ->where('status', 'posted')
                    ->whereYear('date_encoded', $selectedYear)
                    ->whereMonth('date_encoded', $m)
                    ->sum('amount'),
            ];
        }

        $multiYearComparison = [];
        $compareYears = collect($availableYears)->take(4)->sort()->values()->all();
        foreach ($compareYears as $year) {
            $yearIncome = (float) Income::whereYear('date_encoded', $year)->sum('amount');
            $yearAppropriation = (float) BudgetItem::query()
                ->whereHas('budget', fn ($q) => $q->where('year', $year))
                ->sum('appropriation');
            $yearExpense = (float) Disbursement::query()
                ->where('status', 'posted')
                ->whereYear('date_encoded', $year)
                ->sum('amount');

            $multiYearComparison[] = [
                'year' => (int) $year,
                'income' => $yearIncome,
                'appropriation' => $yearAppropriation,
                'expense' => $yearExpense,
                'remainingIncome' => $yearIncome - $yearAppropriation,
                'remainingAppropriation' => $yearAppropriation - $yearExpense,
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
