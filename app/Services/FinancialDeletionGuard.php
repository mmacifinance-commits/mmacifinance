<?php

namespace App\Services;

use App\Models\{AnnualBudget, Disbursement, Expense, Income};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FinancialDeletionGuard
{
    public function check(Model $record): void
    {
        $lock = app(FiscalPeriodLockService::class);
        $reference = $record->income_no ?? $record->ref_no ?? $record->disbursement_no ?? $record->id;
        if ($record instanceof Income) {
            $lock->ensureDateOpen($record->date_encoded);
            if ($record->allocations()->exists()) {
                $this->deny("Cannot delete {$reference}: this income funds budget allocations. Remove or reassign its allocations first.");
            }
            if (filled($record->receipt_no)) {
                $cash = app(CashFlowService::class);
                $periods = AnnualBudget::whereDate('start_date', '<=', $record->date_encoded)
                    ->whereDate('end_date', '>=', $record->date_encoded)->get();
                foreach ([null, ...$periods->all()] as $period) {
                    if (round($cash->totalReceipts($period) - (float) $record->amount - $cash->totalCommittedDisbursements($period), 2) < 0) {
                        $this->deny("Cannot delete {$reference}: the remaining receipts would not cover committed or posted disbursements. Review the linked payments first.");
                    }
                }
            }
        } elseif ($record instanceof Expense) {
            $lock->ensureExpenseOpen($record);
            if ($record->disbursements()->exists()) {
                $this->deny("Cannot delete {$reference}: this expenditure has linked disbursements. Review and remove those disbursements first.");
            }
        } elseif ($record instanceof Disbursement) {
            $lock->ensureDateOpen($record->date_encoded);
            if (!$record->expense) {
                $this->deny("Cannot delete {$reference}: its linked expenditure is missing. Ask the Head of Finance to review this record.");
            }
            $lock->ensureExpenseOpen($record->expense);
            if (in_array($record->status, ['approved', 'posted']) && !auth()->user()?->canApproveDisbursements()) {
                abort(403, 'Only the Head of Finance can delete approved or posted disbursements.');
            }
        }
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['deletion' => $message]);
    }
}
