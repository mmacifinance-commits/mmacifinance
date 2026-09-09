<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\Department;
use App\Services\BudgetUtilizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request, BudgetUtilizationService $utilization)
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category_id' => ['nullable', 'integer', 'exists:budget_categories,id'],
            'account_title_id' => ['nullable', 'integer', 'exists:budget_particulars,id'],
        ]);

        $selectedYear = (int) ($validated['year'] ?? date('Y'));
        $selectedMonth = isset($validated['month']) ? (int) $validated['month'] : null;
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $accountTitleId = isset($validated['account_title_id']) ? (int) $validated['account_title_id'] : null;

        $availableYears = AnnualBudget::pluck('year')
            ->concat([2024, 2025, 2026, (int) date('Y')])
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if ($startDate && $endDate && strtotime($endDate) < strtotime($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $dsbQuery = $utilization->queryForBudgetFilters(
            $selectedYear,
            $selectedMonth,
            $startDate,
            $endDate,
            $departmentId,
            $categoryId,
            $accountTitleId
        )->with(['expense.category', 'expense.particular.department', 'expense.budgetItem', 'approvedBy', 'postedBy']);

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

        $postedDisbursements = (clone $dsbQuery)->get();

        $selectedMonthLabel = 'All Months';
        if ($selectedMonth) {
            $selectedBudgetItems = $annualBudgetItems->where('month', $selectedMonth)->values();
            $selectedMonthLabel = date('F', mktime(0, 0, 0, $selectedMonth, 1));
        } else {
            $selectedBudgetItems = $annualBudgetItems;
        }
        $monthAppropriation = (float) $selectedBudgetItems->sum('appropriation');
        $selectedBudgetItemIds = $selectedBudgetItems->pluck('id')->map(fn ($id) => (int) $id)->all();
        $monthExpenditure = (float) $postedDisbursements
            ->filter(fn ($disbursement) => in_array((int) ($disbursement->expense?->budget_item_id ?? 0), $selectedBudgetItemIds, true))
            ->sum('amount');

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
