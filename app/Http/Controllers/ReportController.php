<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Income;
use App\Services\BudgetUtilizationService;
use App\Services\FiscalPeriodService;
use App\Support\SpreadsheetImportExport;
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
        $report = app(\App\Services\FinancialReportService::class)->build($request);
        $validated = $request->validate([
            'report_type' => ['nullable', 'string', 'in:overall_financial,budget_utilization,cash_receipts,disbursements,income_vs_receipts,fund_balance,responsibility_center,account_title_ledger,closing_report'],
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

        $reportType = $validated['report_type'] ?? 'overall_financial';
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
            $periodMonth = null; // The comparison table is explicitly full fiscal years.
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

        $summaryCards = [
            'totalAppropriation' => round($appropriation, 2),
            'totalReceipts' => $report['totals']['receipts'],
            'postedDisbursements' => $report['totals']['institutionalPostedDisbursements'],
            'budgetBalance' => round($appropriation - $expenditure, 2),
            'cashOnHand' => $report['totals']['cashOnHand'],
            'pendingCommitments' => $report['totals']['pendingCommitments'],
        ];

        $receiptRows = $report['receiptRows'];
        $disbursementRows = $report['disbursementRows'];
        $auditRows = [];
        $warnings = $this->reportWarnings($selectedPeriod, $summaryCards);

        $reportBudgets = AnnualBudget::query()
            ->with(['items' => function ($query) use ($departmentId, $categoryId, $accountTitleId) {
                $this->applyItemDimensions($query, $departmentId, $categoryId, $accountTitleId);
                $query->with(['category', 'particular.department']);
            }])
            ->orderByDesc('start_date')
            ->get();
        BudgetItem::hydrateDerivedTotals($reportBudgets->flatMap->items);

        return Inertia::render('Reports/Index', [
            'reportTypes' => $this->reportTypes(),
            'reportType' => $reportType,
            'budgets' => $reportBudgets,
            'categories' => BudgetCategory::all(),
            'departments' => Department::all(),
            'summaryCards' => $summaryCards,
            'sections' => $report['sections'],
            'reportNotes' => $report['reportNotes'],
            'receiptRows' => $receiptRows,
            'disbursementRows' => $disbursementRows,
            'auditRows' => $auditRows,
            'reconciliationWarnings' => $warnings,
            'selectedMonthPerformance' => $selectedMonthPerformance,
            'budgetPerformanceByYear' => $budgetPerformanceByYear,
            'yearEndUnusedBalances' => $yearEndUnusedBalances,
            'selectedMonthLabel' => $selectedMonthLabel,
            'availableYears' => $periods->pluck('year')->all(),
            'fiscalPeriods' => $fiscalPeriods->options($periods),
            'filters' => [
                'report_type' => $reportType,
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

    public function export(Request $request, \App\Services\FinancialReportService $reports)
    {
        $report = $reports->build($request);
        return SpreadsheetImportExport::downloadFinancialReportXlsx(
            str_replace('_', '-', $report['reportType']).'-report-'.now()->format('Ymd-His'),
            [
                'report_label' => $report['reportLabel'],
                'fiscal_year' => $report['period']?->fiscal_year_label ?? 'No fiscal year selected',
                'fiscal_period' => $report['period']?->period_label ?? 'N/A',
                'allocation_month' => $report['monthLabel'], 'date_range' => $report['dateRangeLabel'],
                'generated_by' => $report['generatedBy']['name'], 'generated_at' => $report['generatedAt'],
                'total_receipts' => $report['totals']['receipts'], 'available_cash' => $report['totals']['cashOnHand'],
                'department' => $report['departmentLabel'], 'category' => $report['categoryLabel'],
                'account_title' => $report['accountTitleLabel'], 'notes' => $report['reportNotes'],
            ],
            $report['sections']
        );
    }

    public function generate(Request $request, \App\Services\FinancialReportService $reports)
    {
        return Inertia::render('Reports/Generated', $reports->build($request));
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

    private function reportTypes(): array
    {
        return [
            ['value' => 'overall_financial', 'label' => 'Overall Financial Report'],
            ['value' => 'budget_utilization', 'label' => 'Budget Utilization Report'],
            ['value' => 'cash_receipts', 'label' => 'Cash Receipts Report'],
            ['value' => 'disbursements', 'label' => 'Disbursement Report'],
            ['value' => 'income_vs_receipts', 'label' => 'Income vs Receipts Report'],
            ['value' => 'fund_balance', 'label' => 'Fund Balance Report'],
            ['value' => 'responsibility_center', 'label' => 'Responsibility Center Report'],
            ['value' => 'account_title_ledger', 'label' => 'Account Title Ledger'],
            ['value' => 'closing_report', 'label' => 'Closing Report'],
        ];
    }


    private function reportWarnings(?AnnualBudget $period, array $summaryCards): array
    {
        $warnings = [];

        if (($summaryCards['cashOnHand'] ?? 0) < 0) {
            $warnings[] = 'Posted disbursements exceed actual cash receipts for the selected fiscal period.';
        }

        if (($summaryCards['budgetBalance'] ?? 0) < 0) {
            $warnings[] = 'Posted disbursements exceed approved budget appropriation.';
        }

        if ($period && Expense::query()->whereBetween('date_encoded', [
            $period->fiscalStart()->toDateString(),
            $period->fiscalEnd()->toDateString(),
        ])->whereNull('budget_item_id')->exists()) {
            $warnings[] = 'One or more expenses inside this fiscal period are not linked to a monthly budget allocation.';
        }

        if ($period && Disbursement::query()->whereHas(
            'expense.budgetItem',
            fn ($query) => $query->where('budget_id', $period->id)
        )->whereIn('status', ['draft', 'for_release', 'for_approval', 'approved', 'returned_for_revision'])->exists()) {
            $warnings[] = 'This fiscal period still has unposted disbursement commitments.';
        }

        return $warnings;
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
