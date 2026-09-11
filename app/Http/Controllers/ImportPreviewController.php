<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Expense;
use App\Services\ImportPreviewService;
use App\Support\SpreadsheetImportExport;
use Illuminate\Http\Request;

class ImportPreviewController extends Controller
{
    public function __invoke(Request $request, string $module, ImportPreviewService $preview)
    {
        $this->authorizePreview($request, $module);

        $request->validate(SpreadsheetImportExport::validationRules('csv_file', true));

        $config = $this->moduleConfig($module);
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

    private function moduleConfig(string $module): ?array
    {
        return match ($module) {
            'budget-categories' => [
                'required' => ['budget_category', 'description'],
                'inspect' => fn (array $row, int $line) => $this->simpleRequired($row, $line, ['budget_category'], $row['budget_category'] ?? null),
            ],
            'departments' => [
                'required' => ['responsibility_center', 'code'],
                'inspect' => fn (array $row, int $line) => $this->simpleRequired($row, $line, ['responsibility_center', 'code'], $row['code'] ?? null),
            ],
            'account-titles' => [
                'required' => ['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title', 'description'],
                'inspect' => fn (array $row, int $line) => $this->accountTitleRow($row, $line),
            ],
            'income' => [
                'required' => ['source', 'description', 'amount', 'date_encoded', 'notes'],
                'inspect' => fn (array $row, int $line) => $this->moneyDateRow($row, $line, ['source', 'description', 'date_encoded'], $row['receipt_no'] ?? implode('|', [$row['source'] ?? '', $row['description'] ?? '', $row['date_encoded'] ?? ''])),
            ],
            'receipts' => [
                'required' => ['receipt_no', 'receipt_type', 'source', 'description', 'amount', 'date_encoded'],
                'inspect' => fn (array $row, int $line) => $this->moneyDateRow($row, $line, ['receipt_no', 'receipt_type', 'source', 'description', 'date_encoded'], $row['receipt_no'] ?? null),
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
                'inspect' => fn (array $row, int $line) => $this->moneyDateRow($row, $line, ['allocation_month', 'budget_category', 'responsibility_center', 'account_title'], implode('|', [$row['allocation_month'] ?? '', $row['account_title'] ?? '']), 'appropriation'),
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

        return ['valid' => true, 'key' => (string) $key, 'message' => 'Ready to import.'];
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

        return ['valid' => true, 'key' => (string) $key, 'message' => 'Ready to import.'];
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

        return ['valid' => true, 'key' => strtolower($row['budget_category'].'|'.$row['responsibility_center'].'|'.$row['account_code'].'|'.$row['account_title']), 'message' => 'Ready to import.'];
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

        return ['valid' => true, 'key' => $row['ref_no'], 'message' => 'Ready to import.'];
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

        return ['valid' => true, 'key' => $row['disbursement_no'], 'message' => 'Ready to import.'];
    }
}
