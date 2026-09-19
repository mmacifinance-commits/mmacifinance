<?php

namespace App\Http\Controllers;

use App\Models\{Disbursement, Expense, Income};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Validation\ValidationException;

class BulkFinancialDeletionController extends Controller
{
    public function __invoke(Request $request, string $module)
    {
        $roles = match ($module) {
            'income' => ['super_admin'],
            'receipts' => ['super_admin', 'budget_officer', 'disbursement_officer', 'cashier'],
            'expenses', 'disbursements' => ['super_admin', 'disbursement_officer', 'cashier'],
            default => [],
        };
        abort_unless(in_array($request->user()->role, $roles), 403);
        $data = $request->validate([
            'scope' => 'required|in:selected,all',
            'ids' => 'required_if:scope,selected|array|max:500',
            'ids.*' => 'required|integer|min:1|distinct',
            'token' => 'nullable|string',
            'confirmation' => 'nullable|string',
        ]);
        $model = match ($module) {
            'income', 'receipts' => Income::class,
            'expenses' => Expense::class,
            'disbursements' => Disbursement::class,
        };
        $controller = match ($module) {
            'income' => IncomeController::class,
            'receipts' => ReceiptController::class,
            'expenses' => ExpenseController::class,
            'disbursements' => DisbursementController::class,
        };

        return DB::transaction(function () use ($request, $data, $model, $module, $controller) {
            $query = $model::query()->orderBy('id');
            if ($module === 'receipts') {
                $query->whereNotNull('receipt_no')->where('receipt_no', '<>', '');
            }
            if ($data['scope'] === 'selected') {
                $query->whereKey($data['ids']);
            }
            $records = $query->lockForUpdate()->get();
            if ($records->isEmpty() || ($data['scope'] === 'selected' && $records->count() !== count($data['ids']))) {
                throw ValidationException::withMessages(['deletion' => 'No records were deleted. The selection is empty or a record is no longer available. Refresh the list.']);
            }
            // Bind confirmation to the exact records and values reviewed, not a changing "all" query.
            $snapshot = hash('sha256', $records->map(fn ($row) => $row->getAttributes())->toJson());
            if (empty($data['token'])) {
                return response()->json([
                    'count' => $records->count(),
                    'amount' => round((float) $records->sum('amount'), 2),
                    'token' => Crypt::encryptString(json_encode([
                        'user' => $request->user()->id, 'module' => $module,
                        'snapshot' => $snapshot, 'expires' => now()->addMinutes(5)->timestamp,
                    ])),
                ]);
            }
            try {
                $preview = json_decode(Crypt::decryptString($data['token']), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['deletion' => 'The deletion confirmation is invalid. Review the selection again.']);
            }
            if (($preview['user'] ?? null) !== $request->user()->id || ($preview['module'] ?? null) !== $module
                || ($preview['snapshot'] ?? null) !== $snapshot || ($preview['expires'] ?? 0) < now()->timestamp) {
                throw ValidationException::withMessages(['deletion' => 'Records changed or the confirmation expired. Nothing was deleted. Review the selection again.']);
            }
            if (($data['confirmation'] ?? '') !== 'DELETE') {
                throw ValidationException::withMessages(['confirmation' => 'Type DELETE to confirm permanent deletion.']);
            }
            foreach ($records as $record) {
                // Reuse single-record authorization, fiscal locks, audit logs and paid-total updates.
                app($controller)->destroy($record, true);
            }
            return response()->json(['message' => $records->count().' record(s) deleted successfully.']);
        });
    }
}
