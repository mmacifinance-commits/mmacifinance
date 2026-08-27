<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\IncomeAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = (int) ($request->query('year') ?: date('Y'));
        $selectedMonth = $request->query('month') ? (int) $request->query('month') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $departmentId = $request->query('department_id') ? (int) $request->query('department_id') : null;
        $categoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;
        $accountTitleId = $request->query('account_title_id') ? (int) $request->query('account_title_id') : null;

        $availableYears = AnnualBudget::pluck('year')
            ->concat([2024, 2025, 2026, (int) date('Y')])
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if ($startDate && $endDate && strtotime($endDate) < strtotime($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // Expenses Query
        $expQuery = Expense::with(['category', 'particular.department']);
        if ($startDate && $endDate) {
            $expQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $expQuery->whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $selectedMonth);
        } else {
            $expQuery->whereYear('date_encoded', $selectedYear);
        }

        if ($categoryId) {
            $expQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $expQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $expQuery->whereHas('particular', fn($q) => $q->where('department_id', $departmentId));
        }

        // Disbursements Query
        $dsbQuery = Disbursement::query()
            ->with(['expense.category', 'expense.particular.department', 'approvedBy', 'postedBy'])
            ->where('status', 'posted');
        if ($startDate && $endDate) {
            $dsbQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $dsbQuery->whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $selectedMonth);
        } else {
            $dsbQuery->whereYear('date_encoded', $selectedYear);
        }

        if ($departmentId || $categoryId || $accountTitleId) {
            $dsbQuery->whereHas('expense', function ($q) use ($departmentId, $categoryId, $accountTitleId) {
                if ($departmentId) {
                    $q->whereHas('particular', fn($p) => $p->where('department_id', $departmentId));
                }
                if ($categoryId) {
                    $q->where('category_id', $categoryId);
                }
                if ($accountTitleId) {
                    $q->where('particular_id', $accountTitleId);
                }
            });
        }

        $annualBudgetItemsQuery = BudgetItem::query()
            ->with(['budget', 'category', 'particular.department'])
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));
        if ($categoryId) {
            $annualBudgetItemsQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $annualBudgetItemsQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $annualBudgetItemsQuery->whereHas('particular', fn ($q) => $q->where('department_id', $departmentId));
        }

        $annualBudgetItems = $annualBudgetItemsQuery->get();
        BudgetItem::hydrateDerivedTotals($annualBudgetItems);

        $selectedBudgetItemIds = $annualBudgetItems
            ->when($selectedMonth, fn ($items) => $items->where('month', $selectedMonth))
            ->pluck('id');

        $postedDisbursementsQuery = Disbursement::query()
            ->with(['expense.category', 'expense.particular.department', 'expense.budgetItem'])
            ->where('status', Disbursement::STATUS_POSTED)
            ->whereHas('expense', fn ($query) => $query->whereIn('budget_item_id', $selectedBudgetItemIds));
        if ($startDate && $endDate) {
            $postedDisbursementsQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        }

        $postedDisbursements = $postedDisbursementsQuery->get();

        $monthlyPostedDisbursements = $postedDisbursements->groupBy(fn ($item) => (int) ($item->expense?->budgetItem?->month ?? 0))
            ->map(fn ($group) => (float) $group->sum('amount'));

        $selectedMonthLabel = 'All Months';
        if ($startDate && $endDate) {
            $startMonth = (int) date('n', strtotime($startDate));
            $endMonth = (int) date('n', strtotime($endDate));
            $selectedBudgetItems = $annualBudgetItems->whereBetween('month', [$startMonth, $endMonth])->values();
            $selectedMonthLabel = date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate));
        } elseif ($selectedMonth) {
            $selectedBudgetItems = $annualBudgetItems->where('month', $selectedMonth)->values();
            $selectedMonthLabel = date('F', mktime(0, 0, 0, $selectedMonth, 1));
        } else {
            $selectedBudgetItems = $annualBudgetItems;
        }
        $monthAppropriation = (float) $selectedBudgetItems->sum('appropriation');
        $monthExpenditure = $startDate && $endDate
            ? (float) $postedDisbursements->sum('amount')
            : ($selectedMonth ? (float) ($monthlyPostedDisbursements[$selectedMonth] ?? 0) : (float) $postedDisbursements->sum('amount'));

        $selectedMonthPerformance = [
            'month_label' => $selectedMonthLabel,
            'appropriation' => $monthAppropriation,
            'expenditure' => $monthExpenditure,
            'utilizationRate' => $monthAppropriation > 0
                ? round(($monthExpenditure / $monthAppropriation) * 100, 2)
                : 0,
        ];

        $performanceItemsQuery = BudgetItem::query()
            ->with(['budget', 'category', 'particular.department'])
            ->whereHas('budget', fn ($q) => $q->whereIn('year', $availableYears));
        if ($selectedMonth) { $performanceItemsQuery->where('month', $selectedMonth); }
        if ($categoryId) { $performanceItemsQuery->where('category_id', $categoryId); }
        if ($accountTitleId) { $performanceItemsQuery->where('particular_id', $accountTitleId); }
        if ($departmentId) { $performanceItemsQuery->whereHas('particular', fn ($q) => $q->where('department_id', $departmentId)); }
        $performanceItems = $performanceItemsQuery->get();
        BudgetItem::hydrateDerivedTotals($performanceItems);

        $budgetPerformanceByYear = collect($availableYears)->map(function ($year) use ($selectedMonth, $performanceItems) {
            $items = $performanceItems->filter(fn ($item) => (int) $item->budget?->year === (int) $year);
            $appropriation = (float) $items->sum('appropriation');
            $expenditure = (float) $items->sum(fn ($item) => $item->postedExpenditureTotal());

            return [
                'year' => (int) $year,
                'selectedMonth' => $selectedMonth,
                'appropriation' => $appropriation,
                'expenditure' => $expenditure,
                'utilizationRate' => $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 2) : 0,
            ];
        })->values();

        // Summarize the exact filtered report dataset into one row per month.
        // Fully utilized months remain included so the totals always reconcile.
        $postedByBudgetItem = $postedDisbursements
            ->groupBy(fn ($item) => (int) ($item->expense?->budget_item_id ?? 0))
            ->map(fn ($group) => (float) $group->sum('amount'));

        $yearEndUnusedBalances = $selectedBudgetItems
            ->groupBy(fn ($item) => (int) $item->month)
            ->map(function ($items, $month) use ($postedByBudgetItem) {
                $appropriation = (float) $items->sum('appropriation');
                $expenditure = (float) $items->sum(fn ($item) => (float) ($postedByBudgetItem[$item->id] ?? 0));

                return [
                    'month' => (int) $month,
                    'month_label' => date('F', mktime(0, 0, 0, max(1, (int) $month), 1)),
                    'appropriation' => round($appropriation, 2),
                    'expenditure' => round($expenditure, 2),
                    'balance' => round($appropriation - $expenditure, 2),
                    'utilization_rate' => $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 2) : 0,
                ];
            })
            ->sortBy('month')
            ->values();

        $selectedMonthLabel = $selectedMonthPerformance['month_label'] ?? ($selectedMonth ? date('F', mktime(0, 0, 0, $selectedMonth, 1)) : 'All Months');

        return Inertia::render('Reports/Index', [
            'budgets' => AnnualBudget::query()
                ->where('year', $selectedYear)
                ->get()
                ->each(function ($budget) use ($annualBudgetItems) {
                    $budget->setRelation('items', $annualBudgetItems->where('budget_id', $budget->id)->values());
                }),
            'categories' => BudgetCategory::all(),
            'departments' => Department::all(),
            'expenses' => $expQuery->latest()->get(),
            'disbursements' => $dsbQuery->latest()->get(),
            'annualBudgetItems' => $annualBudgetItems,
            'budgetItems' => $selectedBudgetItems,
            'selectedMonthPerformance' => $selectedMonthPerformance,
            'budgetPerformanceByYear' => $budgetPerformanceByYear,
            'yearEndUnusedBalances' => $yearEndUnusedBalances,
            'selectedMonthLabel' => $selectedMonthLabel,
            'availableYears' => $availableYears,
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'account_title_id' => $accountTitleId,
            ],
        ]);
    }
}
