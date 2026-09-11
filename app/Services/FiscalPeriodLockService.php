<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\Expense;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class FiscalPeriodLockService
{
    public function closedBudgetForDate(CarbonInterface|string|null $date): ?AnnualBudget
    {
        if (blank($date)) {
            return null;
        }

        $date = Carbon::parse($date)->toDateString();

        return AnnualBudget::query()
            ->whereNotNull('closed_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public function ensureDateOpen(CarbonInterface|string|null $date, string $field = 'date_encoded'): void
    {
        $budget = $this->closedBudgetForDate($date);

        if ($budget) {
            $this->throwClosed($budget, $field);
        }
    }

    public function ensureBudgetOpen(?AnnualBudget $budget, string $field = 'fiscal_period_id'): void
    {
        if ($budget?->closed_at) {
            $this->throwClosed($budget, $field);
        }
    }

    public function ensureExpenseOpen(Expense $expense, string $field = 'expense_id'): void
    {
        $budget = $expense->loadMissing('budgetItem.budget')->budgetItem?->budget;

        $this->ensureBudgetOpen($budget, $field);
    }

    private function throwClosed(AnnualBudget $budget, string $field): void
    {
        throw ValidationException::withMessages([
            $field => "{$budget->fiscal_year_label} is closed. Reopen the fiscal period before changing records in {$budget->period_label}.",
        ]);
    }
}
