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

    public function committedDisbursementQuery(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): Builder
    {
        return Disbursement::query()
            ->whereIn('status', ['draft', 'for_release', 'for_approval', 'approved', Disbursement::STATUS_POSTED, 'returned_for_revision'])
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

    public function totalCommittedDisbursements(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): float
    {
        return (float) $this->committedDisbursementQuery($budget, $exceptDisbursementId)->sum('amount');
    }

    public function cashOnHand(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): float
    {
        return round(
            $this->totalReceipts($budget) - $this->totalPostedDisbursements($budget, $exceptDisbursementId),
            2
        );
    }

    public function availableCashForCommitment(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): float
    {
        return round(
            $this->totalReceipts($budget) - $this->totalCommittedDisbursements($budget, $exceptDisbursementId),
            2
        );
    }

    public function summary(?AnnualBudget $budget = null, ?int $exceptDisbursementId = null): array
    {
        $receipts = $this->totalReceipts($budget);
        $posted = $this->totalPostedDisbursements($budget, $exceptDisbursementId);
        $committed = $this->totalCommittedDisbursements($budget, $exceptDisbursementId);

        return [
            'receipts' => round($receipts, 2),
            'postedDisbursements' => round($posted, 2),
            'committedDisbursements' => round($committed, 2),
            'cashOnHand' => round($receipts - $posted, 2),
            'availableForDisbursement' => round($receipts - $committed, 2),
        ];
    }

    public function ensureSufficientCashForDisbursement(Disbursement $disbursement): void
    {
        $budget = $disbursement->loadMissing('expense.budgetItem.budget')->expense?->budgetItem?->budget;

        if (! $budget) {
            throw ValidationException::withMessages([
                'amount' => 'The disbursement is not linked to a fiscal-period budget allocation.',
            ]);
        }

        $availableCash = $this->lockedAvailableCashForCommitment($budget, $disbursement->id);
        $amount = (float) $disbursement->amount;

        if ($availableCash <= 0 || $amount > $availableCash) {
            throw ValidationException::withMessages([
                'amount' => 'Cannot save this disbursement because available cash on hand is only ₱'.number_format(max(0, $availableCash), 2).'. Add receipts first or reduce the disbursement amount.',
            ]);
        }
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
        $this->lockReceiptRows($budget);

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

    private function lockedAvailableCashForCommitment(AnnualBudget $budget, ?int $exceptDisbursementId = null): float
    {
        $this->lockReceiptRows($budget);

        DB::table('disbursements')
            ->join('expenses', 'disbursements.expense_id', '=', 'expenses.id')
            ->join('budget_items', 'expenses.budget_item_id', '=', 'budget_items.id')
            ->where('budget_items.budget_id', $budget->id)
            ->whereIn('disbursements.status', ['draft', 'for_release', 'for_approval', 'approved', Disbursement::STATUS_POSTED, 'returned_for_revision'])
            ->when($exceptDisbursementId, fn ($query) => $query->where('disbursements.id', '<>', $exceptDisbursementId))
            ->lockForUpdate()
            ->get('disbursements.id');

        return $this->availableCashForCommitment($budget, $exceptDisbursementId);
    }

    private function lockReceiptRows(AnnualBudget $budget): void
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
    }
}
