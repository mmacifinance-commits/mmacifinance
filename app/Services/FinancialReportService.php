<?php

namespace App\Services;

use App\Models\{AnnualBudget, BudgetCategory, BudgetItem, BudgetParticular, Department, Disbursement, Income};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinancialReportService
{
    public const TYPES = [
        'overall_financial' => 'Overall Financial Report', 'budget_utilization' => 'Budget Utilization Report',
        'cash_receipts' => 'Cash Receipts Report', 'disbursements' => 'Disbursement Report',
        'income_vs_receipts' => 'Income vs Receipts Report', 'fund_balance' => 'Fund Balance Report',
        'responsibility_center' => 'Responsibility Center Report', 'account_title_ledger' => 'Account Title Ledger',
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
                : 'PROVISIONAL: this fiscal period is not closed. This report is not a final closing statement.';
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
                'appropriation' => $appropriation, 'expenditure' => $paid, 'balance' => round($appropriation - $paid, 2),
                'utilization_rate' => $appropriation > 0 ? round($paid / $appropriation * 100, 2) : 0];
        });
        $incomeQuery = Income::query()->when($period, fn ($q) => $q->whereBetween('date_encoded', [$period->fiscalStart()->toDateString(), $period->fiscalEnd()->toDateString()]));
        $incomes = $dateFilter(clone $incomeQuery)->orderBy('date_encoded')->orderBy('id')->get();
        $receipts = $incomes->filter(fn ($row) => filled($row->receipt_no));
        $receiptRows = $receipts->map(fn ($row) => ['id' => $row->id, 'income_no' => $row->income_no, 'receipt_no' => $row->receipt_no,
            'receipt_type' => $row->receipt_type, 'source' => $row->source, 'description' => $row->description,
            'amount' => (float) $row->amount, 'receipt_date' => $row->date_encoded?->toDateString()])->values();
        $disbursementRows = $posted->map(fn ($row) => ['id' => $row->id, 'disbursement_no' => $row->disbursement_no,
            'expense_ref' => $row->expense?->ref_no, 'allocation_month' => $row->expense?->budgetItem?->allocation_month?->format('F Y'),
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
            'income' => round((float) $incomes->sum('amount'), 2), 'unreceiptedIncome' => round((float) $incomes->sum('amount') - $cashReceipts, 2)];

        $budgetSection = $this->section('Budget Utilization', ['Allocation Month', 'Monthly Ref.', 'Responsibility Center', 'Category', 'Account Title', 'Appropriation', 'Posted Payments in Range', 'Budget Balance', '% Utilization'],
            $budgetRows->map(fn ($r) => [$r['allocation_month'], $r['ref_no'], $r['responsibility_center'], $r['category'], $r['account_title'], $r['appropriation'], $r['expenditure'], $r['balance'], $r['utilization_rate']])->all(), [5,6,7], 8);
        $budgetSection['balanceColumns'] = [5,6,7];
        $receiptSection = $this->section('Cash Receipts', ['Receipt No.', 'Income No.', 'Receipt Type', 'Source', 'Description', 'Receipt Date', 'Amount'],
            $receiptRows->map(fn ($r) => [$r['receipt_no'], $r['income_no'], $r['receipt_type'], $r['source'], $r['description'], $r['receipt_date'], $r['amount']])->all(), [6]);
        $receiptSection['totalLabel'] = 'TOTAL RECEIPTS';
        $paymentSection = $this->section('Posted Disbursements', ['DSB No.', 'Expense Ref.', 'Allocation Month', 'Expense Date', 'Disbursement Date', 'Payee', 'Status', 'Amount'],
            $disbursementRows->map(fn ($r) => [$r['disbursement_no'], $r['expense_ref'], $r['allocation_month'], $r['expense_date'], $r['disbursement_date'], $r['pay_to'], $r['status'], $r['amount']])->all(), [7]);
        $incomeSection = $this->section('Income vs Receipts', ['Income No.', 'Date', 'Source / Description', 'Receipt No.', 'Recorded Income', 'Receipted Amount', 'Without Receipt No.'],
            $incomes->map(fn ($r) => [$r->income_no, $r->date_encoded?->toDateString(), $r->source.' / '.$r->description, $r->receipt_no,
                (float) $r->amount, filled($r->receipt_no) ? (float) $r->amount : 0, filled($r->receipt_no) ? 0 : (float) $r->amount])->all(), [4,5,6]);
        $fundSection = $this->section('Fund Balance', ['Description', 'Amount'], [
            ['Receipts', $cashReceipts],
            ['Less: Posted Disbursements', $cashPaid], ['Available Cash', $availableCash],
        ], [1]);
        $fundSection['totalLabel'] = null;
        $centers = $budgetRows->groupBy('department_id')->map(function ($rows) {
            $a = (float) $rows->sum('appropriation'); $p = (float) $rows->sum('expenditure');
            return [$rows->first()['responsibility_center'], $a, $p, round($a-$p, 2), $a > 0 ? round($p/$a*100, 2) : 0];
        })->values()->all();
        $centerSection = $this->section('Responsibility Center Summary', ['Responsibility Center', 'Appropriation', 'Posted Payments in Range', 'Budget Balance', '% Utilization'], $centers, [1,2,3], 4);
        $centerSection['balanceColumns'] = [1,2,3];
        $sections = match ($type) {
            'cash_receipts' => [$receiptSection], 'disbursements' => [$paymentSection],
            'budget_utilization' => [$budgetSection], 'income_vs_receipts' => [$incomeSection],
            'fund_balance' => [$fundSection, $receiptSection, $paymentSection],
            'responsibility_center' => [$centerSection, $budgetSection],
            'account_title_ledger' => $this->ledger($items, $posted, $detailPayments, $start),
            'closing_report' => [$fundSection, $budgetSection, $receiptSection, $paymentSection],
            default => [$budgetSection, $receiptSection, $paymentSection],
        };
        if ($type === 'income_vs_receipts') $notes[] = 'Receipted amounts are a subset of Income records, not additional income. Missing receipt numbers do not by themselves establish accounts receivable.';
        if ($type === 'account_title_ledger') $notes[] = 'Budget account-title ledger: appropriation less posted payments, not a double-entry general ledger. Opening balance includes payments before the selected start date.';
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

    private function ledger($items, $posted, $query, ?string $start): array
    {
        $prior = $start ? (clone $query)->whereDate('date_encoded', '<', $start)->with('expense')->get()
            ->groupBy(fn ($r) => $r->expense->budget_item_id)->map(fn ($rows) => (float) $rows->sum('amount')) : collect();
        $sections = [];
        foreach ($items as $item) {
            $balance = round((float) $item->appropriation - ($prior[$item->id] ?? 0), 2);
            $rows = [['Opening budget balance', $item->ref_no, '', '', 0, $balance]];
            foreach ($posted->filter(fn ($r) => $r->expense->budget_item_id === $item->id) as $payment) {
                $balance = round($balance - (float) $payment->amount, 2);
                $rows[] = [$payment->date_encoded?->toDateString(), $payment->disbursement_no, $payment->expense->ref_no, $payment->pay_to, (float) $payment->amount, $balance];
            }
            $section = $this->section(($item->particular?->particular ?? 'Untitled').' / '.($item->particular?->department?->name ?? 'Unassigned').' / '.$item->allocation_month?->format('F Y').' / '.$item->ref_no,
                ['Date / Entry', 'Reference', 'Expense Ref.', 'Payee', 'Posted Payment', 'Budget Balance'], $rows, [4,5]);
            $section['totalLabel'] = null;
            $sections[] = $section;
        }
        return $sections ?: [$this->section('Account Title Ledger', ['Date / Entry', 'Reference', 'Expense Ref.', 'Payee', 'Posted Payment', 'Budget Balance'], [], [4,5])];
    }
}
