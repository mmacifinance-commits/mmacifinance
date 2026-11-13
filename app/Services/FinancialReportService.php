<?php

namespace App\Services;

use App\Models\{AnnualBudget, BudgetCategory, BudgetItem, BudgetParticular, Department, Disbursement, Income};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinancialReportService
{
    public const TYPES = [
        'overall_financial' => 'Overall Financial Report', 'budget_utilization' => 'Budget Utilization Report', 'budget_category' => 'Budget Category Report',
        'cash_receipts' => 'Cash Receipts Report', 'disbursements' => 'Disbursement Report',
        'projected_income' => 'Projected Income Report',
        'income_vs_receipts' => 'Income vs Receipts Report', 'fund_balance' => 'Fund Balance Report',
        'responsibility_center' => 'Responsibility Center Report', 'account_title_ledger' => 'Account Title Report',
        'account_title_summary' => 'Account Title Summary Report',
        'closing_report' => 'Closing Report',
    ];

    public function build(Request $request): array
    {
        $filters = $request->validate([
            'report_type' => 'nullable|in:'.implode(',', array_keys(self::TYPES)),
            'fiscal_period_id' => 'nullable|integer|exists:annual_budgets,id',
            'year' => 'nullable|integer|min:2000|max:2100', 'month' => 'nullable|integer|between:1,12',
            'allocation_month' => 'nullable|date_format:Y-m-d',
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d',
            'department_id' => 'nullable|integer|exists:departments,id',
            'category_id' => 'nullable|integer|exists:budget_categories,id',
            'account_title_id' => 'nullable|integer|exists:budget_particulars,id',
        ]);
        $type = $filters['report_type'] ?? 'overall_financial';
        $fiscal = app(FiscalPeriodService::class);
        $period = $fiscal->resolve($filters['fiscal_period_id'] ?? null, $filters['year'] ?? null);
        $month = $period ? $fiscal->allocationMonth($period, $filters['allocation_month'] ?? null, $filters['month'] ?? null) : null;
        if ($period && !empty($filters['allocation_month']) && !$month) {
            throw ValidationException::withMessages(['allocation_month' => 'Choose an allocation month inside the selected fiscal year.']);
        }
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        if ($start && $end && $start > $end) {
            [$start, $end] = [$end, $start];
        }
        $department = $filters['department_id'] ?? null;
        $category = $filters['category_id'] ?? null;
        $account = $filters['account_title_id'] ?? null;
        $notes = [
            'Expenditure and cash payments include posted disbursements only; draft, rejected and other unposted releases are excluded.',
            'Date range uses receipt dates for cash received and disbursement dates for payments. Allocation month filters budget allocations and linked payments, not receipt dates.',
            'Appropriation is the approved allocation, not income or cash received. Budget balance is appropriation less posted payments in the selected date range.',
            'Responsibility center, category and account title filters apply to budget and payment details, not income or receipts.',
        ];
        if ($type === 'closing_report') {
            $notes[] = $period?->closed_at
                ? 'Closed fiscal period. This is a report of current stored records, not an immutable historical snapshot.'
                : 'PROVISIONAL: this fiscal period is not closed. This report is not a final closing report.';
            if ($start || $end || $month || $department || $category || $account) {
                throw ValidationException::withMessages(['report_type' => 'A closing report must cover the full fiscal year. Clear date, month, responsibility center, category and account-title filters.']);
            }
        }
        if (!$period) $notes[] = 'No fiscal period exists. Budget and payment sections are empty; income and cash receipts use the date range only.';

        $itemsQuery = BudgetItem::query()->with(['category:id,name', 'particular.department:id,name'])
            ->where('budget_id', $period?->id ?? 0)
            ->when($month, fn ($q) => $q->whereDate('allocation_month', $month))
            ->when($department, fn ($q) => $q->whereHas('particular', fn ($q) => $q->where('department_id', $department)))
            ->when($category, fn ($q) => $q->where('category_id', $category))
            ->when($account, fn ($q) => $q->where('particular_id', $account));
        $items = $itemsQuery->orderBy('allocation_month')->orderBy('id')->get();
        $payments = Disbursement::query()->where('status', Disbursement::STATUS_POSTED)
            ->whereHas('expense.budgetItem', fn ($q) => $q->where('budget_id', $period?->id ?? 0));
        $detailPayments = (clone $payments)->whereHas('expense', fn ($q) => $q->whereIn('budget_item_id', $items->modelKeys()));
        $dateFilter = fn ($q) => $q->when($start, fn ($q) => $q->whereDate('date_encoded', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('date_encoded', '<=', $end));
        $posted = $dateFilter(clone $detailPayments)->with('expense.budgetItem')->orderBy('date_encoded')->orderBy('id')->get();
        $postedByItem = $posted->groupBy(fn ($row) => $row->expense->budget_item_id)->map(fn ($rows) => (float) $rows->sum('amount'));
        $budgetRows = $items->map(function ($item) use ($postedByItem) {
            $appropriation = (float) $item->appropriation;
            $paid = round($postedByItem[$item->id] ?? 0, 2);
            return ['ref_no' => $item->ref_no, 'allocation_month' => $item->allocation_month?->format('F Y'),
                'department_id' => $item->particular?->department_id,
                'responsibility_center' => $item->particular?->department?->name ?? 'Unassigned',
                'category' => $item->category?->name ?? 'Uncategorized', 'account_title' => $item->particular?->particular ?? 'Untitled',
                'particulars' => $item->particulars,
                'appropriation' => $appropriation, 'expenditure' => $paid, 'balance' => round($appropriation - $paid, 2),
                'utilization_rate' => $appropriation > 0 ? round($paid / $appropriation * 100, 2) : 0];
        });
        $incomeQuery = Income::with(['budgetCategory', 'department'])->when($period, fn ($q) => $q->whereBetween('date_encoded', [$period->fiscalStart()->toDateString(), $period->fiscalEnd()->toDateString()]));
        $records = $dateFilter(clone $incomeQuery)->orderBy('date_encoded')->orderBy('id')->get();
        $receipts = $records->filter(fn ($row) => filled($row->receipt_no));
        $incomes = $records->reject(fn ($row) => filled($row->receipt_no));
        $receiptRows = $receipts->map(fn ($row) => ['id' => $row->id, 'income_no' => $row->income_no, 'receipt_no' => $row->receipt_no,
            'budget_category' => $row->budgetCategory?->name ?? 'Uncategorized', 'responsibility_center' => $row->department?->name ?? 'Unassigned',
            'receipt_type' => $row->receipt_type, 'source' => $row->source, 'description' => $row->description,
            'amount' => (float) $row->amount, 'receipt_date' => $row->date_encoded?->toDateString()])->values();
        $disbursementRows = $posted->map(fn ($row) => ['id' => $row->id, 'disbursement_no' => $row->disbursement_no,
            'expense_ref' => $row->expense?->ref_no, 'allocation_month' => $row->expense?->budgetItem?->allocation_month?->format('F Y'),
            'particulars' => $row->expense?->budgetItem?->particulars,
            'expense_date' => $row->expense?->date_encoded?->toDateString(), 'disbursement_date' => $row->date_encoded?->toDateString(),
            'pay_to' => $row->pay_to, 'amount' => (float) $row->amount, 'status' => $row->status])->values();
        // Cash balances must use all institutional payments, not a department subset.
        $cashReceipts = round((float) $receipts->sum('amount'), 2);
        $cashPaid = round((float) $dateFilter(clone $payments)->sum('amount'), 2);
        $availableCash = round($cashReceipts - $cashPaid, 2);
        $pending = Disbursement::query()->whereIn('status', ['draft', 'for_release', 'for_approval', 'approved', 'returned_for_revision'])
            ->whereHas('expense', fn ($q) => $q->whereIn('budget_item_id', $items->modelKeys()));
        $notes[] = 'Available cash equals receipts less posted disbursements for the selected dates. Pending commitments use current workflow status, not historical status.';
        $totals = ['appropriation' => round((float) $budgetRows->sum('appropriation'), 2), 'expenditure' => round((float) $budgetRows->sum('expenditure'), 2),
            'balance' => round((float) $budgetRows->sum('balance'), 2), 'receipts' => $cashReceipts,
            'postedDisbursements' => round((float) $posted->sum('amount'), 2), 'cashOnHand' => $availableCash,
            'institutionalPostedDisbursements' => $cashPaid,
            'pendingCommitments' => round((float) $dateFilter($pending)->sum('amount'), 2),
            'income' => round((float) $incomes->sum('amount'), 2)];

        $budgetSection = $this->section('Budget Utilization', ['Allocation Month', 'Monthly Ref.', 'Responsibility Center', 'Category', 'Account Title', 'Particulars', 'Appropriation', 'Posted Payments in Range', 'Budget Balance', '% Utilization'],
            $budgetRows->map(fn ($r) => [$r['allocation_month'], $r['ref_no'], $r['responsibility_center'], $r['category'], $r['account_title'], $r['particulars'], $r['appropriation'], $r['expenditure'], $r['balance'], $r['utilization_rate']])->all(), [6,7,8], 9);
        $budgetSection['balanceColumns'] = [6,7,8];
        $receiptSection = $this->section('Cash Receipts', ['Receipt No.', 'Income No.', 'Receipt Type', 'Source', 'Description', 'Receipt Date', 'Amount', 'Budget Category', 'Responsibility Center'],
            $receiptRows->map(fn ($r) => [$r['receipt_no'], $r['income_no'], $r['receipt_type'], $r['source'], $r['description'], $r['receipt_date'], $r['amount'], $r['budget_category'], $r['responsibility_center']])->all(), [6]);
        $receiptSection['type'] = 'receipts';
        $receiptSection['totalLabel'] = 'TOTAL RECEIPTS';
        $paymentSection = $this->section('Posted Disbursements', ['DSB No.', 'Expense Ref.', 'Allocation Month', 'Particulars', 'Expense Date', 'Disbursement Date', 'Payee', 'Status', 'Amount'],
            $disbursementRows->map(fn ($r) => [$r['disbursement_no'], $r['expense_ref'], $r['allocation_month'], $r['particulars'], $r['expense_date'], $r['disbursement_date'], $r['pay_to'], $r['status'], $r['amount']])->all(), [8]);
        $paymentSection['type'] = 'disbursements';
        $incomeSection = $this->section('Projected Income', ['Income No.', 'Date', 'Source', 'Description', 'Amount', 'Budget Category', 'Responsibility Center'],
            $incomes->map(fn ($r) => [$r->income_no, $r->date_encoded?->toDateString(), $r->source, $r->description, (float) $r->amount, $r->budgetCategory?->name ?? 'Uncategorized', $r->department?->name ?? 'Unassigned'])->values()->all(), [4]);
        $incomeSummarySection = $this->section('Projected Income Summary', ['Account Title', 'Projected Income'],
            $incomes->groupBy(fn ($r) => trim((string) $r->source) ?: 'Unnamed Account Title')
                ->map(fn ($rows, $source) => [$source, round((float) $rows->sum('amount'), 2)])->values()->all(), [1]);
        $incomeSummarySection['totalLabel'] = 'TOTAL PROJECTED INCOME';
        $comparisonSection = $this->section('Income vs Receipts', ['Description', 'Amount'], [
            ['Projected Income', $totals['income']], ['Actual Receipts', $cashReceipts],
            ['Difference (Projected Income less Receipts)', round($totals['income'] - $cashReceipts, 2)],
        ], [1]);
        $comparisonSection['totalLabel'] = null;
        $comparisonSummarySection = $this->section('Income vs Receipts Summary', ['Projected Income', 'Actual Receipts', 'Difference', 'Collection Rate (%)'], [
            [$totals['income'], $cashReceipts, round($totals['income'] - $cashReceipts, 2), $totals['income'] > 0 ? round($cashReceipts / $totals['income'] * 100, 2) : null],
        ], [0, 1, 2], 3);
        $comparisonSummarySection['totalLabel'] = 'TOTAL';
        $comparisonSummarySection['totals'][3] = $totals['income'] > 0 ? round($cashReceipts / $totals['income'] * 100, 2) : null;
        $categoryComparison = $this->section('Income vs Receipts by Budget Category', ['Budget Category', 'Projected Income', 'Actual Receipts', 'Difference', 'Collection Rate (%)'],
            $records->groupBy(fn ($record) => $record->budget_category_id ?? 'unassigned')->map(function ($rows) {
                $projected = (float) $rows->filter(fn ($record) => blank($record->receipt_no))->sum('amount');
                $actual = (float) $rows->filter(fn ($record) => filled($record->receipt_no))->sum('amount');
                return [$rows->first()->budgetCategory?->name ?? 'Uncategorized', $projected, $actual, round($projected - $actual, 2), $projected > 0 ? round($actual / $projected * 100, 2) : null];
            })->values()->all(), [1,2,3], 4);

        $fundSection = $this->section('Fund Balance', ['Description', 'Amount'], [
            ['Receipts', $cashReceipts],
            ['Less: Posted Disbursements', $cashPaid], ['Available Cash', $availableCash],
        ], [1]);
        $fundSection['totalLabel'] = null;
        $centers = $budgetRows->groupBy('department_id')->map(function ($rows) {
            $a = (float) $rows->sum('appropriation'); $p = (float) $rows->sum('expenditure');
            return [$rows->first()['responsibility_center'], $a, $p, round($a-$p, 2), $a > 0 ? round($p/$a*100, 2) : 0];
        })->values()->all();
        $centerSection = $this->section('Responsibility Center Summary', ['Responsibility Center', 'Total Budget', 'Total Expenditure', 'Total Remaining', '% Utilization'], $centers, [1,2,3], 4);
        $centerSection['balanceColumns'] = [1,2,3];
        $categories = $budgetRows->groupBy('category')->map(function ($rows) {
            $a = (float) $rows->sum('appropriation'); $p = (float) $rows->sum('expenditure');
            return [$rows->first()['category'], $a, $p, round($a - $p, 2), $a > 0 ? round($p / $a * 100, 2) : 0];
        })->values()->all();
        $categorySection = $this->section('Budget Category Summary', ['Budget Category', 'Total Budget', 'Total Expenditure', 'Total Remaining', '% Utilization'], $categories, [1,2,3], 4);
        $categorySection['balanceColumns'] = [1,2,3];
        $accountTitleSection = $this->accountSections($items, $posted, $detailPayments, $start)[0];
        $accountTitleSection['totals'][7] = ($accountTitleSection['totals'][3] ?? 0) > 0
            ? round(($accountTitleSection['totals'][5] ?? 0) / $accountTitleSection['totals'][3] * 100, 2) : 0;
        $accountTitleSummarySection = $this->section('Account Title Summary',
            ['Account Title', 'Appropriation', 'Opening Budget Balance', 'Posted Payments in Range', 'Closing Budget Balance', '% Utilization'],
            array_map(fn ($row) => [$row[0], $row[3], $row[4], $row[5], $row[6], $row[3] > 0 ? round($row[5] / $row[3] * 100, 2) : 0], $accountTitleSection['rows']), [1,2,3,4], 5);
        $accountTitleSummarySection['totals'][5] = ($accountTitleSummarySection['totals'][1] ?? 0) > 0
            ? round(($accountTitleSummarySection['totals'][3] ?? 0) / $accountTitleSummarySection['totals'][1] * 100, 2) : 0;
        $sections = match ($type) {
            'cash_receipts' => [$receiptSection], 'disbursements' => [$paymentSection],
            'projected_income' => [$incomeSummarySection, $incomeSection],
            'budget_utilization' => [$budgetSection], 'budget_category' => [$categorySection, $budgetSection], 'income_vs_receipts' => [$comparisonSummarySection, $comparisonSection, $categoryComparison, $incomeSection, $receiptSection],
            'fund_balance' => [$fundSection, $receiptSection, $paymentSection],
            'responsibility_center' => [$centerSection, $budgetSection],
            'account_title_ledger' => [...$this->accountSections($items, $posted, $detailPayments, $start), $paymentSection],
            'account_title_summary' => [$accountTitleSummarySection],
            'closing_report' => [...$this->closingSections($period, $totals), $centerSection, $this->accountSections($items, $posted, $detailPayments, null)[0]],
            default => [$centerSection, $budgetSection, $receiptSection, $paymentSection],
        };
        if ($type === 'income_vs_receipts') $notes[] = 'Income is projected. Receipts are actual collections, not additional projected income. The difference is a comparison, not a list of unpaid debts.';
        if ($type === 'account_title_ledger') $notes[] = 'Account Title Report: appropriation less posted disbursements. Opening balance includes disbursements before the selected start date.';
        if ($availableCash < 0) $notes[] = 'Warning: available cash is negative. Review receipts and posted disbursements.';
        return ['reportType' => $type, 'reportLabel' => self::TYPES[$type], 'period' => $period,
            'monthLabel' => $month ? Carbon::parse($month)->format('F Y') : 'All Fiscal Months',
            'dateRangeLabel' => ($start ?: 'Start of fiscal year').' to '.($end ?: 'End of fiscal year'),
            'departmentLabel' => $department ? Department::find($department)?->name : 'All Responsibility Centers',
            'categoryLabel' => $category ? BudgetCategory::find($category)?->name : 'All Categories',
            'accountTitleLabel' => $account ? BudgetParticular::find($account)?->particular : 'All Account Titles',
            'rows' => $budgetRows, 'receiptRows' => $receiptRows, 'disbursementRows' => $disbursementRows,
            'totals' => $totals, 'sections' => $sections, 'reportNotes' => $notes,
            'generatedAt' => now()->toIso8601String(), 'generatedBy' => ['name' => $request->user()?->name ?? 'System'],
            'reconciliationWarnings' => []];
    }

    private function section(string $title, array $headers, array $rows, array $money, ?int $percent = null): array
    {
        $totals = [];
        foreach ($money as $column) $totals[$column] = round(array_sum(array_column($rows, $column)), 2);
        if ($percent !== null) $totals[$percent] = ($totals[$money[0]] ?? 0) > 0 ? round($totals[$money[1]] / $totals[$money[0]] * 100, 2) : 0;
        return compact('title', 'headers', 'rows', 'money', 'percent', 'totals') + ['type' => 'unified', 'totalLabel' => 'TOTAL'];
    }

    private function accountSections($items, $posted, $query, ?string $start): array
    {
        $prior = $start ? (clone $query)->whereDate('date_encoded', '<', $start)->with('expense')->get()
            ->groupBy(fn ($r) => $r->expense->budget_item_id)->map(fn ($rows) => (float) $rows->sum('amount')) : collect();
        $paid = $posted->groupBy(fn ($r) => $r->expense->budget_item_id)->map(fn ($rows) => (float) $rows->sum('amount'));
        $summary = [];
        $detailRows = [];
        foreach ($items->groupBy(fn ($item) => $this->accountTitleKey($item->particular?->particular)) as $allocations) {
            $first = $allocations->first();
            $title = $first->particular?->particular ?? 'Untitled';
            $center = $first->particular?->department?->name ?? 'Unassigned';
            $category = $first->category?->name ?? 'Uncategorized';
            $rows = $allocations->groupBy(fn ($item) => $item->particulars ?: 'Unspecified')
                ->map(function ($particularAllocations) use ($prior, $paid) {
                    $appropriation = (float) $particularAllocations->sum('appropriation');
                    $opening = (float) $particularAllocations->sum(fn ($item) => (float) $item->appropriation - ($prior[$item->id] ?? 0));
                    $payments = (float) $particularAllocations->sum(fn ($item) => $paid[$item->id] ?? 0);
                    $singleAllocation = $particularAllocations->count() === 1 ? $particularAllocations->first() : null;
                    return [$particularAllocations->first()->particulars ?: 'Unspecified',
                        $singleAllocation?->allocation_month?->format('F Y') ?? 'All Fiscal Months',
                        $singleAllocation?->ref_no ?? 'Multiple allocations',
                        round($appropriation, 2), round($opening, 2), round($payments, 2), round($opening - $payments, 2)];
                })->values()->all();
            $detail = $this->section($title.' — '.$center.' — '.$category,
                ['Particulars', 'Allocation Month', 'Monthly Ref.', 'Appropriation', 'Opening Budget Balance', 'Posted Payments in Range', 'Closing Budget Balance'], $rows, [3,4,5,6]);
            $summary[] = [$title, $center, $category, $detail['totals'][3], $detail['totals'][4], $detail['totals'][5], $detail['totals'][6]];
            foreach ($rows as $row) $detailRows[] = [$title, ...$row];
        }
        $breakdown = $this->section('Account Title Breakdown',
            ['Account Title', 'Particulars', 'Allocation Month', 'Monthly Ref.', 'Appropriation', 'Opening Budget Balance', 'Posted Payments in Range', 'Closing Budget Balance'],
            $detailRows, [4,5,6,7]);
        $summarySection = $this->section('Account Title Summary',
            ['Account Title', 'Responsibility Center', 'Category', 'Appropriation', 'Opening Budget Balance', 'Posted Payments in Range', 'Closing Budget Balance', '% Utilization'],
            array_map(fn ($row) => [...$row, $row[3] > 0 ? round($row[5] / $row[3] * 100, 2) : 0], $summary), [3,4,5,6], 7);
        $summarySection['totals'][7] = ($summarySection['totals'][3] ?? 0) > 0
            ? round(($summarySection['totals'][5] ?? 0) / $summarySection['totals'][3] * 100, 2) : 0;
        return [$summarySection, $breakdown];
    }

    private function accountTitleKey(?string $title): string
    {
        $title = strtolower(trim((string) $title));
        $title = preg_replace('/\s+/', ' ', $title);
        return preg_replace('/s$/', '', $title);
    }

    private function closingSections(?AnnualBudget $period, array $totals): array
    {
        $status = $this->section('Fiscal Year Closing Status', ['Item', 'Status'], [
            ['Fiscal Period', $period?->period_label ?? 'No fiscal period selected'],
            ['Closing Status', $period?->closed_at ? 'CLOSED' : 'PROVISIONAL — fiscal period is still open'],
            ['Record Basis', 'Current stored records as of generation time'],
            ['Outstanding Commitments', $totals['pendingCommitments'] > 0 ? 'Unposted commitments remain for review' : 'No unposted commitments'],
        ], []);
        $status['totalLabel'] = null;
        $budget = $this->section('Year-End Budget Summary', ['Description', 'Amount'], [
            ['Total Appropriation', $totals['appropriation']],
            ['Less: Posted Disbursements', $totals['postedDisbursements']],
            ['Remaining Budget Balance', $totals['balance']],
            ['Unposted Commitments', $totals['pendingCommitments']],
            ['Budget Balance after Commitments', round($totals['balance'] - $totals['pendingCommitments'], 2)],
        ], [1]);
        $budget['totalLabel'] = null;
        $cash = $this->section('Fiscal Year Cash Summary', ['Description', 'Amount'], [
            ['Cash Receipts during Fiscal Year', $totals['receipts']],
            ['Less: Posted Disbursements', $totals['institutionalPostedDisbursements']],
            ['Net Cash Movement (Receipts less Payments)', $totals['cashOnHand']],
            ['Unposted Commitments', $totals['pendingCommitments']],
            ['Net Cash Movement after Commitments', round($totals['cashOnHand'] - $totals['pendingCommitments'], 2)],
        ], [1]);
        $cash['totalLabel'] = null;
        return [$status, $budget, $cash];
    }
}
