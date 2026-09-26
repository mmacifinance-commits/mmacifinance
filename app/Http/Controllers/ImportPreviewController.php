<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Income;
use App\Services\ImportPreviewService;
use App\Support\SpreadsheetImportExport;
use Illuminate\Http\Request;

class ImportPreviewController extends Controller
{
    public function __invoke(Request $request, string $module, ImportPreviewService $preview)
    {
        $this->authorizePreview($request, $module);

        $request->validate(SpreadsheetImportExport::validationRules('csv_file', true));

        $config = $this->moduleConfig($module, $request);
        abort_unless($config, 404);

        try {
            return response()->json($preview->preview(
                $request->file('csv_file'),
                $config['required'],
                $config['inspect']
            ));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'headers' => [],
                'missing_columns' => [],
                'valid_count' => 0,
                'invalid_count' => 1,
                'duplicate_count' => 0,
                'valid_rows' => [],
                'invalid_rows' => [['line' => null, 'message' => $exception->getMessage(), 'row' => []]],
                'duplicates' => [],
            ], 422);
        }
    }

    private function authorizePreview(Request $request, string $module): void
    {
        $user = $request->user();
        $allowed = match ($module) {
            'budget-categories', 'account-titles', 'annual-budget-items' => $user?->canManageBudget(),
            'departments', 'income' => $user?->isSuperAdmin(),
            'receipts' => in_array($user?->role, ['super_admin', 'budget_officer', 'disbursement_officer', 'cashier'], true),
            'expenses', 'disbursements' => $user?->canManageDisbursements(),
            default => false,
        };

        abort_unless($allowed, 403);
    }

    private function moduleConfig(string $module, Request $request): ?array
    {
        $annualBudget = $module === 'annual-budget-items'
            ? AnnualBudget::find($request->integer('annual_budget_id'))
            : null;

        return match ($module) {
            'budget-categories' => [
                'required' => ['budget_category', 'description'],
                'inspect' => fn (array $row, int $line) => $this->categoryRow($row, $line),
            ],
            'departments' => [
                'required' => ['responsibility_center', 'code'],
                'inspect' => fn (array $row, int $line) => $this->departmentRow($row, $line),
            ],
            'account-titles' => [
                'required' => ['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title', 'description'],
                'inspect' => fn (array $row, int $line) => $this->accountTitleRow($row, $line),
            ],
            'income' => [
                'required' => ['source', 'description', 'amount', 'date_encoded', 'notes'],
                'inspect' => fn (array $row, int $line) => filled($row['receipt_no'] ?? null) || filled($row['receipt_type'] ?? null)
                    ? ['valid' => false, 'key' => $row['receipt_no'] ?? null, 'message' => 'Import receipt records from the Receipts page. Income is projected income.']
                    : $this->incomeRow($row, $line),
            ],
            'receipts' => [
                'required' => ['receipt_no', 'receipt_type', 'source', 'description', 'amount', 'date_encoded'],
                'inspect' => fn (array $row, int $line) => $this->receiptRow($row, $line),
            ],
            'expenses' => [
                'required' => ['ref_no', 'description', 'category', 'account_title', 'amount', 'date_encoded', 'date_approved', 'status', 'notes'],
                'inspect' => fn (array $row, int $line) => $this->expenseRow($row, $line),
            ],
            'disbursements' => [
                'required' => ['disbursement_no', 'expense_ref_no', 'description', 'source', 'pay_to', 'amount', 'method', 'date_encoded', 'status', 'notes', 'remarks'],
                'inspect' => fn (array $row, int $line) => $this->disbursementRow($row, $line),
            ],
            'annual-budget-items' => [
                'required' => ['allocation_month', 'budget_category', 'responsibility_center', 'account_title', 'appropriation'],
                'inspect' => fn (array $row, int $line) => $this->annualBudgetItemRow($row, $line, $annualBudget),
            ],
            default => null,
        };
    }

    private function simpleRequired(array $row, int $line, array $required, mixed $key): array
    {
        foreach ($required as $column) {
            if (trim((string) ($row[$column] ?? '')) === '') {
                return ['valid' => false, 'key' => $key, 'message' => "Line {$line} is missing {$column}."];
            }
        }

        return ['valid' => true, 'key' => (string) $key, 'action' => 'new', 'message' => 'New record will be created.'];
    }

    private function moneyDateRow(array $row, int $line, array $required, mixed $key, string $amountColumn = 'amount'): array
    {
        $requiredCheck = $this->simpleRequired($row, $line, $required, $key);
        if (! $requiredCheck['valid']) {
            return $requiredCheck;
        }

        if (! is_numeric($row[$amountColumn] ?? null) || (float) $row[$amountColumn] <= 0) {
            return ['valid' => false, 'key' => $key, 'message' => "Line {$line} must have a positive {$amountColumn}."];
        }

        foreach (['date_encoded', 'allocation_month'] as $dateColumn) {
            if (isset($row[$dateColumn]) && \Illuminate\Support\Facades\Validator::make([$dateColumn => $row[$dateColumn]], [$dateColumn => 'required|date'])->fails()) {
                return ['valid' => false, 'key' => $key, 'message' => "Line {$line} has an invalid {$dateColumn}."];
            }
        }

        return ['valid' => true, 'key' => (string) $key, 'action' => 'new', 'message' => 'New record will be created.'];
    }

    private function categoryRow(array $row, int $line): array
    {
        $check = $this->simpleRequired($row, $line, ['budget_category'], $row['budget_category'] ?? null);
        if (! $check['valid']) return $check;
        $exists = BudgetCategory::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $row['budget_category']))])->exists();
        return $this->withAction($check, $exists, 'budget category');
    }

    private function departmentRow(array $row, int $line): array
    {
        $check = $this->simpleRequired($row, $line, ['responsibility_center', 'code'], $row['code'] ?? null);
        if (! $check['valid']) return $check;
        $exists = Department::where('code', strtoupper(trim((string) $row['code'])))->exists();
        return $this->withAction($check, $exists, 'responsibility center');
    }

    private function incomeRow(array $row, int $line): array
    {
        $key = implode('|', [$row['source'] ?? '', $row['description'] ?? '', $row['date_encoded'] ?? '']);
        $check = $this->moneyDateRow($row, $line, ['source', 'description', 'date_encoded'], $key);
        if (! $check['valid']) return $check;
        $exists = Income::projected()->where('source', trim((string) $row['source']))->where('description', trim((string) $row['description']))->whereDate('date_encoded', $row['date_encoded'])->exists();
        return $this->withAction($check, $exists, 'projected income record');
    }

    private function receiptRow(array $row, int $line): array
    {
        $check = $this->moneyDateRow($row, $line, ['receipt_no', 'receipt_type', 'source', 'description', 'date_encoded'], $row['receipt_no'] ?? null);
        if (! $check['valid']) return $check;
        return $this->withAction($check, Income::where('receipt_no', trim((string) $row['receipt_no']))->exists(), 'receipt');
    }

    private function withAction(array $check, bool $exists, string $record): array
    {
        $check['action'] = $exists ? 'update' : 'new';
        $check['message'] = $exists ? "Will update the existing {$record}." : "New {$record} will be created.";
        return $check;
    }

    private function accountTitleRow(array $row, int $line): array
    {
        $check = $this->simpleRequired($row, $line, ['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title'], $row['account_code'] ?? null);
        if (! $check['valid']) {
            return $check;
        }

        $category = BudgetCategory::where('name', $row['budget_category'])->orWhere('id', $row['budget_category'])->exists();
        $department = Department::where('code', $row['responsibility_center'])->orWhere('name', $row['responsibility_center'])->orWhere('id', $row['responsibility_center'])->exists();

        if (! $category || ! $department) {
            return ['valid' => false, 'key' => $row['account_code'], 'message' => "Line {$line} has missing category or responsibility center reference."];
        }

        $key = strtolower($category->id.'|'.$department->id.'|'.trim((string) $row['account_code']).'|'.trim((string) $row['account_title']));
        $exists = BudgetParticular::where('category_id', $category->id)
            ->where('department_id', $department->id)
            ->where('account_code', trim((string) $row['account_code']))
            ->where('particular', trim((string) $row['account_title']))
            ->exists();

        return $this->withAction(['valid' => true, 'key' => $key], $exists, 'account title');
    }

    private function expenseRow(array $row, int $line): array
    {
        $check = $this->moneyDateRow($row, $line, ['ref_no', 'description', 'category', 'account_title', 'date_encoded'], $row['ref_no'] ?? null);
        if (! $check['valid']) {
            return $check;
        }

        if (! BudgetCategory::where('name', $row['category'])->orWhere('id', $row['category'])->exists()) {
            return ['valid' => false, 'key' => $row['ref_no'], 'message' => "Line {$line} references an unknown category."];
        }

        if (! BudgetParticular::where('particular', $row['account_title'])->orWhere('account_name', $row['account_title'])->exists()) {
            return ['valid' => false, 'key' => $row['ref_no'], 'message' => "Line {$line} references an unknown account title."];
        }

        return $this->withAction($check, Expense::where('ref_no', trim((string) $row['ref_no']))->exists(), 'expenditure');
    }

    private function disbursementRow(array $row, int $line): array
    {
        $check = $this->moneyDateRow($row, $line, ['disbursement_no', 'expense_ref_no', 'description', 'source', 'pay_to', 'date_encoded'], $row['disbursement_no'] ?? null);
        if (! $check['valid']) {
            return $check;
        }

        if (! Expense::where('ref_no', $row['expense_ref_no'])->where('status', 'approved')->exists()) {
            return ['valid' => false, 'key' => $row['disbursement_no'], 'message' => "Line {$line} needs an approved expense first."];
        }

        return $this->withAction($check, Disbursement::where('disbursement_no', trim((string) $row['disbursement_no']))->exists(), 'disbursement');
    }

    private function annualBudgetItemRow(array $row, int $line, ?AnnualBudget $annualBudget): array
    {
        if (! $annualBudget) {
            return ['valid' => false, 'key' => null, 'message' => 'Select an annual budget before previewing this file.'];
        }

        $key = implode('|', [$row['allocation_month'] ?? '', $row['budget_category'] ?? '', $row['responsibility_center'] ?? '', $row['account_title'] ?? '']);
        $check = $this->moneyDateRow($row, $line, ['allocation_month', 'budget_category', 'responsibility_center', 'account_title'], $key, 'appropriation');
        if (! $check['valid']) return $check;

        $category = BudgetCategory::where('name', trim((string) $row['budget_category']))->orWhere('id', $row['budget_category'])->first();
        $department = Department::where('code', trim((string) $row['responsibility_center']))
            ->orWhere('name', trim((string) $row['responsibility_center']))
            ->orWhere('id', $row['responsibility_center'])
            ->first();
        $account = $category && $department
            ? BudgetParticular::where('category_id', $category->id)
                ->where('department_id', $department->id)
                ->where(fn ($query) => $query->where('particular', trim((string) $row['account_title']))->orWhere('account_name', trim((string) $row['account_title'])))
                ->first()
            : null;

        if (! $account) {
            return ['valid' => true, 'key' => $key, 'action' => 'new', 'message' => 'New allocation will be created. Related setup records will be added if needed.'];
        }

        $exists = $annualBudget->items()
            ->where('particular_id', $account->id)
            ->whereDate('allocation_month', $row['allocation_month'])
            ->exists();
        if ($exists) {
            return ['valid' => false, 'key' => $key, 'message' => 'This monthly allocation already exists. Edit it from Annual Budget instead of importing it again.'];
        }

        return ['valid' => true, 'key' => $key, 'action' => 'new', 'message' => 'New allocation will be created.'];
    }
}
