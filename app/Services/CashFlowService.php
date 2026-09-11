<?php

namespace App\Services;

use App\Models\AnnualBudget;
use App\Models\Disbursement;
use App\Models\Income;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashFlowService
{
    public function receiptQuery(?AnnualBudget $budget = null): Builder
    {
        return Income::query()
            ->whereNotNull('receipt_no')
            ->where('receipt_no', '<>', '')
            ->when($budget, fn (Builder $query) => $query->whereBetween('date_encoded', [
                $budget->fiscalStart()->toDateString(),
                $budget->fiscalEnd()->toDateString(),
            ]));
    }

    public function postedDisbursementQuery(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): Builder
    {
        return Disbursement::query()
            ->where('status', Disbursement::STATUS_POSTED)
            ->when($exceptDisbursementId, fn (Builder $query) => $query->whereKeyNot($exceptDisbursementId))
            ->when($budget, fn (Builder $query) => $query->whereHas(
                'expense.budgetItem',
                fn (Builder $itemQuery) => $itemQuery->where('budget_id', $budget->id)
            ));
    }

    public function totalReceipts(?AnnualBudget $budget = null): float
    {
        return (float) $this->receiptQuery($budget)->sum('amount');
    }

    public function totalPostedDisbursements(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): float
    {
        return (float) $this->postedDisbursementQuery($budget, $exceptDisbursementId)->sum('amount');
    }

    public function cashOnHand(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): float
    {
        return round(
            $this->totalReceipts($budget) - $this->totalPostedDisbursements($budget, $exceptDisbursementId),
            2
        );
    }

    public function ensureSufficientCashForPosting(Disbursement $disbursement): void
    {
        $budget = $disbursement->loadMissing('expense.budgetItem.budget')->expense?->budgetItem?->budget;

        if (! $budget) {
            throw ValidationException::withMessages([
                'amount' => 'The disbursement is not linked to a fiscal-period budget allocation.',
            ]);
        }

        $availableCash = $this->lockedCashOnHand($budget, $disbursement->id);
        $amount = (float) $disbursement->amount;

        if ($amount > $availableCash) {
            throw ValidationException::withMessages([
                'amount' => 'Cannot post this disbursement because actual cash on hand is only ₱'.number_format($availableCash, 2).'.',
            ]);
        }
    }

    private function lockedCashOnHand(AnnualBudget $budget, ?int $exceptDisbursementId = null): float
    {
        DB::table('incomes')
            ->whereNotNull('receipt_no')
            ->where('receipt_no', '<>', '')
            ->whereBetween('date_encoded', [
                $budget->fiscalStart()->toDateString(),
                $budget->fiscalEnd()->toDateString(),
            ])
            ->lockForUpdate()
            ->get('id');

        DB::table('disbursements')
            ->join('expenses', 'disbursements.expense_id', '=', 'expenses.id')
            ->join('budget_items', 'expenses.budget_item_id', '=', 'budget_items.id')
            ->where('budget_items.budget_id', $budget->id)
            ->where('disbursements.status', Disbursement::STATUS_POSTED)
            ->when($exceptDisbursementId, fn ($query) => $query->where('disbursements.id', '<>', $exceptDisbursementId))
            ->lockForUpdate()
            ->get('disbursements.id');

        return $this->cashOnHand($budget, $exceptDisbursementId);
    }
}
