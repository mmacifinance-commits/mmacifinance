<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\Disbursement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BudgetUtilizationService
{
    public const POSTED_STATUS = 'posted';

    public function postedDisbursements(): Builder
    {
        return Disbursement::query()->where('disbursements.status', self::POSTED_STATUS);
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
        $year = (int) date('Y', strtotime($date));
        $month = (int) date('n', strtotime($date));

        $candidates = BudgetItem::query()
            ->where('category_id', $categoryId)
            ->where('particular_id', $particularId)
            ->whereHas('budget', fn (Builder $query) => $query->where('year', $year))
            ->get();

        $sameMonth = $candidates->where('month', $month);
        if ($sameMonth->count() === 1) {
            return $sameMonth->first();
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
