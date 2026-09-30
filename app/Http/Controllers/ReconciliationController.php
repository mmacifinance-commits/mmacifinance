<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Disbursement;
use App\Models\Reconciliation;
use App\Services\CashFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReconciliationController extends Controller
{
    public function index()
    {
        return Inertia::render('Reconciliations/Index', [
            'records' => Reconciliation::latest('id')->paginate(20),
            'canCreate' => in_array(auth()->user()->role, ['super_admin', 'cashier']),
        ]);
    }

    public function store(Request $request, CashFlowService $cashFlow)
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'cashier']), 403);
        $data = $request->validate([
            'as_of_date' => 'required|date|before_or_equal:today',
            'opening_balance' => 'required|numeric|between:-999999999999,999999999999|decimal:0,2',
            'actual_cash' => 'required|numeric|between:0,999999999999|decimal:0,2',
            'bank_balance' => 'required|numeric|between:-999999999999,999999999999|decimal:0,2',
            'deposits_in_transit' => 'required|numeric|between:0,999999999999|decimal:0,2',
            'outstanding_payments' => 'required|numeric|between:0,999999999999|decimal:0,2',
            'notes' => 'nullable|string|max:3000',
        ]);
        DB::transaction(function () use ($data, $request, $cashFlow) {
            $receipts = (int) round((float) $cashFlow->receiptQuery()->whereDate('date_encoded', '<=', $data['as_of_date'])->sum('amount') * 100);
            $payments = (int) round((float) Disbursement::where('status', 'posted')->whereDate('date_encoded', '<=', $data['as_of_date'])->sum('amount') * 100);
            $cents = fn ($key) => (int) round((float) $data[$key] * 100);
            $book = $cents('opening_balance') + $receipts - $payments;
            $actual = $cents('actual_cash') + $cents('bank_balance') + $cents('deposits_in_transit') - $cents('outstanding_payments');
            if ($actual !== $book && empty(trim($data['notes'] ?? ''))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['notes' => 'Explain the difference before saving this reconciliation.']);
            }
            $record = Reconciliation::create($data + [
                'receipts' => $receipts / 100, 'payments' => $payments / 100,
                'book_balance' => $book / 100, 'difference' => ($actual - $book) / 100,
                'created_by_id' => $request->user()->id,
                'created_by_name' => $request->user()->name,
                'created_by_role' => $request->user()->role_label,
            ]);
            AuditTrail::log($record, 'reconciled', $request->user(), $data['notes'] ?? 'Cash and bank reconciliation recorded.', $record->toArray());
        });

        return back()->with('success', 'Reconciliation saved. Recorded balances are preserved as a snapshot.');
    }
}
