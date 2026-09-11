<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use App\Models\Income;
use App\Services\BudgetUtilizationService;
use App\Services\FiscalPeriodService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevenueController extends Controller
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

        $budgetItems = BudgetItem::query()
            ->when($selectedPeriod, fn ($query) => $query->where('budget_id', $selectedPeriod->id))
            ->when($allocationMonth, fn ($query) => $query->whereDate('allocation_month', $allocationMonth))
            ->with(['budget', 'category', 'particular.department'])
            ->get();
        BudgetItem::hydrateDerivedTotals($budgetItems);

        $incomeQuery = Income::query()
            ->when($selectedPeriod, fn ($query) => $query->whereBetween('date_encoded', [
                $selectedPeriod->fiscalStart()->toDateString(),
                $selectedPeriod->fiscalEnd()->toDateString(),
            ]));
        if ($startDate) {
            $incomeQuery->whereDate('date_encoded', '>=', $startDate);
        }
        if ($endDate) {
            $incomeQuery->whereDate('date_encoded', '<=', $endDate);
        }
        if (! $startDate && ! $endDate && $allocationMonth) {
            $monthStart = Carbon::parse($allocationMonth);
            $incomeQuery->whereBetween('date_encoded', [
                $monthStart->toDateString(),
                $monthStart->copy()->endOfMonth()->toDateString(),
            ]);
        }
        $incomeRecords = (clone $incomeQuery)->get();

        $postedQuery = $selectedPeriod
            ? $utilization->queryForAnnualBudgetFilters(
                $selectedPeriod,
                $allocationMonth,
                $startDate,
                $endDate
            )
            : null;
        $totalIncome = (float) $incomeQuery->sum('amount');
        $totalAppropriation = (float) $budgetItems->sum('appropriation');
        $totalExpense = (float) ($postedQuery?->sum('amount') ?? 0);

        $monthRows = collect($selectedPeriod?->orderedFiscalMonths() ?? []);
        $incomeByMonth = $incomeRecords
            ->groupBy(fn (Income $income) => $income->date_encoded->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));
        $appropriationByMonth = $budgetItems
            ->groupBy(fn (BudgetItem $item) => $item->allocation_month?->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('appropriation'));
        $expenseByMonth = $selectedPeriod
            ? $utilization->totalsByFiscalAllocationMonth($selectedPeriod)
            : collect();
        if ($allocationMonth) {
            $expenseByMonth = $expenseByMonth->only($allocationMonth);
        }

        $monthlyIncome = $monthRows->map(fn ($month) => [
            'month' => $month['short_label'],
            'month_label' => $month['label'],
            'allocation_month' => $month['value'],
            'amount' => (float) ($incomeByMonth[substr($month['value'], 0, 7)] ?? 0),
        ])->all();
        $monthlyAppropriation = $monthRows->map(fn ($month) => [
            'month' => $month['short_label'],
            'month_label' => $month['label'],
            'allocation_month' => $month['value'],
            'amount' => (float) ($appropriationByMonth[substr($month['value'], 0, 7)] ?? 0),
        ])->all();
        $monthlyExpense = $monthRows->map(fn ($month) => [
            'month' => $month['short_label'],
            'month_label' => $month['label'],
            'allocation_month' => $month['value'],
            'amount' => (float) ($expenseByMonth[$month['value']] ?? 0),
        ])->all();

        $comparisonPeriods = $periods->take(4)->sortBy('start_date')->values();
        $multiYearComparison = $comparisonPeriods->map(function ($period) use ($utilization) {
            $income = (float) Income::query()->whereBetween('date_encoded', [
                $period->fiscalStart()->toDateString(),
                $period->fiscalEnd()->toDateString(),
            ])->sum('amount');
            $appropriation = (float) BudgetItem::query()
                ->where('budget_id', $period->id)
                ->sum('appropriation');
            $expense = (float) $utilization->queryForAnnualBudgetFilters($period)->sum('amount');

            return [
                'id' => $period->id,
                'year' => $period->year,
                'label' => $period->fiscal_year_label,
                'income' => $income,
                'appropriation' => $appropriation,
                'expense' => $expense,
                'remainingIncome' => $income - $appropriation,
                'remainingAppropriation' => $appropriation - $expense,
            ];
        })->all();

        return Inertia::render('Revenue/Index', [
            'availableYears' => $periods->pluck('year')->all(),
            'fiscalPeriods' => $fiscalPeriods->options($periods),
            'filters' => [
                'year' => $selectedPeriod?->year,
                'fiscal_period_id' => $selectedPeriod?->id,
                'month' => $allocationMonth ? (int) date('n', strtotime($allocationMonth)) : null,
                'allocation_month' => $allocationMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'stats' => [
                'totalIncome' => $totalIncome,
                'totalRevenue' => $totalAppropriation,
                'totalExpense' => $totalExpense,
                'balance' => $totalAppropriation - $totalExpense,
                'remainingIncome' => $totalIncome - $totalAppropriation,
                'remainingIncomeAfterExpense' => $totalIncome - $totalExpense,
                'utilizationRate' => $totalAppropriation > 0
                    ? round(($totalExpense / $totalAppropriation) * 100, 2)
                    : 0,
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
