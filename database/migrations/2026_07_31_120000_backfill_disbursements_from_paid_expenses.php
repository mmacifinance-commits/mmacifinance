<?php

use App\Models\Disbursement;
use App\Models\Expense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $expenses = Expense::query()
                ->with(['category', 'particular.department'])
                ->where('paid', '>', 0)
                ->whereDoesntHave('disbursements')
                ->orderBy('date_encoded')
                ->get();

            $lastNumber = Disbursement::query()
                ->whereNotNull('disbursement_no')
                ->orderByDesc('id')
                ->value('disbursement_no');

            $next = 1;
            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $next = ((int) $matches[1]) + 1;
            }

            foreach ($expenses as $expense) {
                $disbursementNo = 'DSB' . str_pad((string) $next++, 8, '0', STR_PAD_LEFT);
                $amount = (float) $expense->paid;

                Disbursement::create([
                    'disbursement_no' => $disbursementNo,
                    'expense_id' => $expense->id,
                    'description' => $expense->description,
                    'source' => $expense->category?->name ?? 'Expense',
                    'pay_to' => $expense->particular?->particular ?? 'MMAC Accredited Vendor / Service Provider',
                    'amount' => $amount,
                    'method' => 'bank_transfer',
                    'date_encoded' => $expense->date_encoded,
                    'date_approved' => $expense->date_approved ?: $expense->date_encoded,
                    'status' => 'posted',
                    'notes' => 'Backfilled from existing paid expense record.',
                    'remarks' => 'Backfilled from existing paid expense record.',
                    'prepared_by_id' => null,
                    'released_by_id' => null,
                    'submitted_by_id' => null,
                    'approved_by_id' => null,
                    'rejected_by_id' => null,
                    'posted_by_id' => null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Disbursement::query()
            ->where('remarks', 'Backfilled from existing paid expense record.')
            ->delete();
    }
};
