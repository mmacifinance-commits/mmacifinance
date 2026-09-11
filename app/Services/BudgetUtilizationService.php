<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\Disbursement;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BudgetUtilizationService
{
    public function postedDisbursements(): Builder
    {
        return Disbursement::query()->where('disbursements.status', Disbursement::STATUS_POSTED);
    }

    public function queryForBudgetFilters(
        int $year,
        ?int $month = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $departmentId = null,
        ?int $categoryId = null,
        ?int $particularId = null
    ): Builder {
        $budget = AnnualBudget::query()->where('year', $year)->first();
        if (! $budget) {
            return $this->postedDisbursements()->whereRaw('1 = 0');
        }

        $allocationMonth = $month ? $budget->allocationMonthForNumber($month)?->toDateString() : null;

        return $this->queryForAnnualBudgetFilters(
            $budget,
            $allocationMonth,
            $startDate,
            $endDate,
            $departmentId,
            $categoryId,
            $particularId
        );
    }

    public function queryForAnnualBudgetFilters(
        AnnualBudget $budget,
        ?string $allocationMonth = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $departmentId = null,
        ?int $categoryId = null,
        ?int $particularId = null
    ): Builder {
        return $this->postedDisbursements()
            ->whereHas('expense.budgetItem', fn (Builder $query) => $query->where('budget_id', $budget->id))
            ->when($allocationMonth, fn (Builder $query) => $query->whereHas(
                'expense.budgetItem',
                fn (Builder $itemQuery) => $itemQuery->whereDate('allocation_month', $allocationMonth)
            ))
            ->when($startDate, fn (Builder $query) => $query->whereDate('disbursements.date_encoded', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('disbursements.date_encoded', '<=', $endDate))
            ->when($categoryId, fn (Builder $query) => $query->whereHas(
                'expense.budgetItem',
                fn (Builder $itemQuery) => $itemQuery->where('category_id', $categoryId)
            ))
            ->when($particularId, fn (Builder $query) => $query->whereHas(
                'expense.budgetItem',
                fn (Builder $itemQuery) => $itemQuery->where('particular_id', $particularId)
            ))
            ->when($departmentId, fn (Builder $query) => $query->whereHas(
                'expense.budgetItem.particular',
                fn (Builder $particularQuery) => $particularQuery->where('department_id', $departmentId)
            ));
    }

    public function totalForBudgetFilters(
        int $year,
        ?int $month = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $departmentId = null,
        ?int $categoryId = null,
        ?int $particularId = null
    ): float {
        return (float) $this->queryForBudgetFilters(
            $year,
            $month,
            $startDate,
            $endDate,
            $departmentId,
            $categoryId,
            $particularId
        )->sum('disbursements.amount');
    }

    public function totalsByAllocationMonth(
        int $year,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $departmentId = null,
        ?int $categoryId = null,
        ?int $particularId = null
    ): Collection {
        $query = $this->postedDisbursements()
            ->join('expenses', 'disbursements.expense_id', '=', 'expenses.id')
            ->join('budget_items', 'expenses.budget_item_id', '=', 'budget_items.id')
            ->join('annual_budgets', 'budget_items.budget_id', '=', 'annual_budgets.id')
            ->where('annual_budgets.year', $year);

        $this->applyJoinedBudgetFilters($query, $startDate, $endDate, $departmentId, $categoryId, $particularId);

        return $query
            ->selectRaw('budget_items.month as month, SUM(disbursements.amount) as total')
            ->groupBy('budget_items.month')
            ->pluck('total', 'month');
    }

    public function totalsByFiscalAllocationMonth(
        AnnualBudget $budget,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $departmentId = null,
        ?int $categoryId = null,
        ?int $particularId = null
    ): Collection {
        $query = $this->postedDisbursements()
            ->join('expenses', 'disbursements.expense_id', '=', 'expenses.id')
            ->join('budget_items', 'expenses.budget_item_id', '=', 'budget_items.id')
            ->where('budget_items.budget_id', $budget->id);

        $this->applyJoinedBudgetFilters($query, $startDate, $endDate, $departmentId, $categoryId, $particularId);

        return $query
            ->selectRaw('budget_items.allocation_month as allocation_month, SUM(disbursements.amount) as total')
            ->groupBy('budget_items.allocation_month')
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::parse($row->allocation_month)->toDateString() => (float) $row->total,
            ]);
    }

    public function totalsByBudgetYear(
        array $years,
        ?int $departmentId = null,
        ?int $categoryId = null,
        ?int $particularId = null
    ): Collection {
        if ($years === []) {
            return collect();
        }

        $query = $this->postedDisbursements()
            ->join('expenses', 'disbursements.expense_id', '=', 'expenses.id')
            ->join('budget_items', 'expenses.budget_item_id', '=', 'budget_items.id')
            ->join('annual_budgets', 'budget_items.budget_id', '=', 'annual_budgets.id')
            ->whereIn('annual_budgets.year', $years);

        $this->applyJoinedBudgetFilters($query, null, null, $departmentId, $categoryId, $particularId);

        return $query
            ->selectRaw('annual_budgets.year as year, SUM(disbursements.amount) as total')
            ->groupBy('annual_budgets.year')
            ->pluck('total', 'year');
    }

    private function applyJoinedBudgetFilters(
        Builder $query,
        ?string $startDate,
        ?string $endDate,
        ?int $departmentId,
        ?int $categoryId,
        ?int $particularId
    ): void {
        if ($startDate) {
            $query->whereDate('disbursements.date_encoded', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('disbursements.date_encoded', '<=', $endDate);
        }
        if ($categoryId) {
            $query->where('budget_items.category_id', $categoryId);
        }
        if ($particularId) {
            $query->where('budget_items.particular_id', $particularId);
        }
        if ($departmentId) {
            $query->whereExists(function ($departmentQuery) use ($departmentId) {
                $departmentQuery->selectRaw('1')
                    ->from('budget_particulars')
                    ->whereColumn('budget_particulars.id', 'budget_items.particular_id')
                    ->where('budget_particulars.department_id', $departmentId);
            });
        }
    }

    public function expenditureForItem(BudgetItem $item): float
    {
        if (! $item->exists) {
            return 0.0;
        }

        return (float) $this->postedDisbursements()
            ->whereHas('expense', fn (Builder $query) => $query->where('budget_item_id', $item->getKey()))
            ->sum('amount');
    }

    public function availableForExpense(BudgetItem $item, ?Expense $expense = null): float
    {
        $postedForAllocation = $this->expenditureForItem($item);
        $postedForCurrentExpense = 0.0;

        if ($expense?->exists && (int) $expense->budget_item_id === (int) $item->getKey()) {
            $postedForCurrentExpense = (float) $this->postedDisbursements()
                ->where('expense_id', $expense->getKey())
                ->sum('amount');
        }

        return round(
            max(0, (float) $item->appropriation - $postedForAllocation + $postedForCurrentExpense),
            2
        );
    }

    public function hydrateItems(Collection $items): void
    {
        $itemIds = $items->pluck('id')->filter()->unique()->values();
        if ($itemIds->isEmpty()) {
            return;
        }

        $postedByItem = $this->postedDisbursements()
            ->join('expenses', 'disbursements.expense_id', '=', 'expenses.id')
            ->whereIn('expenses.budget_item_id', $itemIds)
            ->selectRaw('expenses.budget_item_id, SUM(disbursements.amount) as total')
            ->groupBy('expenses.budget_item_id')
            ->pluck('total', 'expenses.budget_item_id');

        $items->each(function (BudgetItem $item) use ($postedByItem) {
            $expenditure = (float) ($postedByItem[$item->id] ?? 0);
            $appropriation = (float) $item->appropriation;

            $item->setAttribute('derived_expenditure', $expenditure);
            $item->setAttribute('expenditure', $expenditure);
            $item->setAttribute('balance', round($appropriation - $expenditure, 2));
            $item->setAttribute(
                'utilization_rate',
                $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 2) : 0.0
            );
        });
    }

    public function resolveBudgetItem(int $categoryId, int $particularId, string $date): ?BudgetItem
    {
        $budget = $this->annualBudgetForDate($date);
        if (! $budget) {
            return null;
        }

        $allocationMonth = date('Y-m-01', strtotime($date));

        $candidates = BudgetItem::query()
            ->where('category_id', $categoryId)
            ->where('particular_id', $particularId)
            ->where('budget_id', $budget->id)
            ->get();

        $sameMonth = $candidates->filter(
            fn (BudgetItem $item) => $item->allocation_month?->format('Y-m-d') === $allocationMonth
        );
        if ($sameMonth->count() === 1) {
            return $sameMonth->first();
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    public function annualBudgetForDate(string $date): ?AnnualBudget
    {
        return AnnualBudget::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('start_date')
            ->first();
    }
}
