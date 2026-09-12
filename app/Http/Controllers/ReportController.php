<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\Department;
use App\Services\BudgetUtilizationService;
use App\Services\FiscalPeriodService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(
        Request $request,
        BudgetUtilizationService $utilization,
        FiscalPeriodService $fiscalPeriods
    ) {
        $validated = $request->validate([
            'fiscal_period_id' => ['nullable', 'integer', 'exists:annual_budgets,id'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'allocation_month' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category_id' => ['nullable', 'integer', 'exists:budget_categories,id'],
            'account_title_id' => ['nullable', 'integer', 'exists:budget_particulars,id'],
        ]);

        $periods = $fiscalPeriods->all();
        $selectedPeriod = $fiscalPeriods->resolve(
            isset($validated['fiscal_period_id']) ? (int) $validated['fiscal_period_id'] : null,
            isset($validated['year']) ? (int) $validated['year'] : null
        );
        $allocationMonth = $selectedPeriod
            ? $fiscalPeriods->allocationMonth(
                $selectedPeriod,
                $validated['allocation_month'] ?? null,
                isset($validated['month']) ? (int) $validated['month'] : null
            )
            : null;
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $accountTitleId = isset($validated['account_title_id']) ? (int) $validated['account_title_id'] : null;

        if ($startDate && $endDate && $endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $itemsQuery = BudgetItem::query()
            ->with(['budget', 'category', 'particular.department'])
            ->when($selectedPeriod, fn ($query) => $query->where('budget_id', $selectedPeriod->id))
            ->when($allocationMonth, fn ($query) => $query->whereDate('allocation_month', $allocationMonth));
        $this->applyItemDimensions($itemsQuery, $departmentId, $categoryId, $accountTitleId);
        $selectedItems = $itemsQuery->get();
        BudgetItem::hydrateDerivedTotals($selectedItems);

        $postedDisbursements = $selectedPeriod
            ? $utilization->queryForAnnualBudgetFilters(
                $selectedPeriod,
                $allocationMonth,
                $startDate,
                $endDate,
                $departmentId,
                $categoryId,
                $accountTitleId
            )->with([
                'expense.category',
                'expense.particular.department',
                'expense.budgetItem',
                'approvedBy',
                'postedBy',
            ])->get()
            : collect();

        $appropriation = (float) $selectedItems->sum('appropriation');
        $expenditure = (float) $postedDisbursements->sum('amount');
        $selectedMonthLabel = $allocationMonth
            ? Carbon::parse($allocationMonth)->format(
                $selectedPeriod?->fiscalStart()->year === $selectedPeriod?->fiscalEnd()->year ? 'F' : 'F Y'
            )
            : 'All Fiscal Months';
        $selectedMonthPerformance = [
            'month_label' => $selectedMonthLabel,
            'appropriation' => $appropriation,
            'expenditure' => $expenditure,
            'utilizationRate' => $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 2) : 0,
        ];

        $budgetPerformanceByYear = $periods->map(function (AnnualBudget $period) use (
            $utilization,
            $allocationMonth,
            $departmentId,
            $categoryId,
            $accountTitleId
        ) {
            $periodMonth = $allocationMonth && $period->containsDate($allocationMonth) ? $allocationMonth : null;
            $itemsQuery = BudgetItem::query()
                ->where('budget_id', $period->id)
                ->when($periodMonth, fn ($query) => $query->whereDate('allocation_month', $periodMonth));
            $this->applyItemDimensions($itemsQuery, $departmentId, $categoryId, $accountTitleId);
            $periodAppropriation = (float) $itemsQuery->sum('appropriation');
            $periodExpenditure = (float) $utilization->queryForAnnualBudgetFilters(
                $period,
                $periodMonth,
                null,
                null,
                $departmentId,
                $categoryId,
                $accountTitleId
            )->sum('amount');

            return [
                'id' => $period->id,
                'year' => $period->year,
                'label' => $period->fiscal_year_label,
                'selectedMonth' => $periodMonth,
                'appropriation' => $periodAppropriation,
                'expenditure' => $periodExpenditure,
                'utilizationRate' => $periodAppropriation > 0
                    ? round(($periodExpenditure / $periodAppropriation) * 100, 2)
                    : 0,
            ];
        })->values();

        $postedByBudgetItem = $postedDisbursements
            ->groupBy(fn ($item) => (int) ($item->expense?->budget_item_id ?? 0))
            ->map(fn ($rows) => (float) $rows->sum('amount'));
        $yearEndUnusedBalances = $selectedItems
            ->groupBy(fn (BudgetItem $item) => $item->allocation_month?->format('Y-m-d'))
            ->map(function ($items, $month) use ($postedByBudgetItem) {
                $monthAppropriation = (float) $items->sum('appropriation');
                $monthExpenditure = (float) $items->sum(
                    fn ($item) => (float) ($postedByBudgetItem[$item->id] ?? 0)
                );

                return [
                    'month' => $month,
                    'allocation_month' => $month,
                    'month_label' => $items->first()?->allocation_month?->format('F Y') ?? 'Unknown',
                    'appropriation' => round($monthAppropriation, 2),
                    'expenditure' => round($monthExpenditure, 2),
                    'balance' => round($monthAppropriation - $monthExpenditure, 2),
                    'utilization_rate' => $monthAppropriation > 0
                        ? round(($monthExpenditure / $monthAppropriation) * 100, 2)
                        : 0,
                ];
            })
            ->sortBy('allocation_month')
            ->values();

        $reportBudgets = AnnualBudget::query()
            ->with(['items' => function ($query) use ($departmentId, $categoryId, $accountTitleId) {
                $this->applyItemDimensions($query, $departmentId, $categoryId, $accountTitleId);
                $query->with(['category', 'particular.department']);
            }])
            ->orderByDesc('start_date')
            ->get();
        BudgetItem::hydrateDerivedTotals($reportBudgets->flatMap->items);

        return Inertia::render('Reports/Index', [
            'budgets' => $reportBudgets,
            'categories' => BudgetCategory::all(),
            'departments' => Department::all(),
            'selectedMonthPerformance' => $selectedMonthPerformance,
            'budgetPerformanceByYear' => $budgetPerformanceByYear,
            'yearEndUnusedBalances' => $yearEndUnusedBalances,
            'selectedMonthLabel' => $selectedMonthLabel,
            'availableYears' => $periods->pluck('year')->all(),
            'fiscalPeriods' => $fiscalPeriods->options($periods),
            'filters' => [
                'year' => $selectedPeriod?->year,
                'fiscal_period_id' => $selectedPeriod?->id,
                'month' => $allocationMonth ? (int) date('n', strtotime($allocationMonth)) : null,
                'allocation_month' => $allocationMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'account_title_id' => $accountTitleId,
            ],
        ]);
    }

    public function generate(
        Request $request,
        BudgetUtilizationService $utilization,
        FiscalPeriodService $fiscalPeriods
    ) {
        $validated = $request->validate([
            'fiscal_period_id' => ['nullable', 'integer', 'exists:annual_budgets,id'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'allocation_month' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category_id' => ['nullable', 'integer', 'exists:budget_categories,id'],
            'account_title_id' => ['nullable', 'integer', 'exists:budget_particulars,id'],
        ]);

        $selectedPeriod = $fiscalPeriods->resolve(
            isset($validated['fiscal_period_id']) ? (int) $validated['fiscal_period_id'] : null,
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        $allocationMonth = $selectedPeriod
            ? $fiscalPeriods->allocationMonth(
                $selectedPeriod,
                $validated['allocation_month'] ?? null,
                isset($validated['month']) ? (int) $validated['month'] : null
            )
            : null;

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        if ($startDate && $endDate && $endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $accountTitleId = isset($validated['account_title_id']) ? (int) $validated['account_title_id'] : null;

        $itemsQuery = BudgetItem::query()
            ->with(['budget', 'category', 'particular.department'])
            ->when($selectedPeriod, fn ($query) => $query->where('budget_id', $selectedPeriod->id))
            ->when($allocationMonth, fn ($query) => $query->whereDate('allocation_month', $allocationMonth));
        $this->applyItemDimensions($itemsQuery, $departmentId, $categoryId, $accountTitleId);
        $items = $itemsQuery
            ->orderBy('allocation_month')
            ->orderBy('category_id')
            ->orderBy('particular_id')
            ->get();

        BudgetItem::hydrateDerivedTotals($items);

        $postedDisbursements = $selectedPeriod
            ? $utilization->queryForAnnualBudgetFilters(
                $selectedPeriod,
                $allocationMonth,
                $startDate,
                $endDate,
                $departmentId,
                $categoryId,
                $accountTitleId
            )->with(['expense.budgetItem'])->get()
            : collect();

        $postedByBudgetItem = $postedDisbursements
            ->groupBy(fn ($item) => (int) ($item->expense?->budget_item_id ?? 0))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $rows = $items->map(function (BudgetItem $item) use ($postedByBudgetItem) {
            $appropriation = (float) $item->appropriation;
            $expenditure = (float) ($postedByBudgetItem[$item->id] ?? 0);

            return [
                'ref_no' => $item->ref_no,
                'allocation_month' => $item->allocation_month?->format('F Y') ?? 'Unknown',
                'responsibility_center' => $item->particular?->department?->name ?? 'No Responsibility Center',
                'category' => $item->category?->name ?? 'Uncategorized',
                'account_title' => $item->particular?->particular ?? 'Untitled',
                'appropriation' => $appropriation,
                'expenditure' => $expenditure,
                'balance' => $appropriation - $expenditure,
                'utilization_rate' => $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 2) : 0,
            ];
        });

        $department = $departmentId ? Department::find($departmentId) : null;
        $category = $categoryId ? BudgetCategory::find($categoryId) : null;
        $monthLabel = $allocationMonth ? Carbon::parse($allocationMonth)->format('F Y') : 'All Fiscal Months';

        return Inertia::render('Reports/Generated', [
            'period' => $selectedPeriod,
            'monthLabel' => $monthLabel,
            'dateRangeLabel' => $this->dateRangeLabel($startDate, $endDate),
            'departmentLabel' => $department?->name ?? 'All Responsibility Centers',
            'categoryLabel' => $category?->name ?? 'All Categories',
            'rows' => $rows,
            'totals' => [
                'appropriation' => (float) $rows->sum('appropriation'),
                'expenditure' => (float) $rows->sum('expenditure'),
                'balance' => (float) $rows->sum('balance'),
            ],
            'generatedAt' => now(),
            'generatedBy' => auth()->user(),
        ]);
    }

    private function applyItemDimensions($query, ?int $departmentId, ?int $categoryId, ?int $accountTitleId): void
    {
        $query
            ->when($categoryId, fn ($itemQuery) => $itemQuery->where('category_id', $categoryId))
            ->when($accountTitleId, fn ($itemQuery) => $itemQuery->where('particular_id', $accountTitleId))
            ->when($departmentId, fn ($itemQuery) => $itemQuery->whereHas(
                'particular',
                fn ($particularQuery) => $particularQuery->where('department_id', $departmentId)
            ));
    }

    private function dateRangeLabel(?string $startDate, ?string $endDate): string
    {
        if ($startDate && $endDate) {
            return "Posted {$startDate} to {$endDate}";
        }

        if ($startDate) {
            return "Posted from {$startDate}";
        }

        if ($endDate) {
            return "Posted through {$endDate}";
        }

        return 'All posting dates';
    }
}
