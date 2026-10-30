<?php

namespace App\Http\Controllers;

use App\Models\{AnnualBudget, AuditTrail, IncomeQuota};
use Illuminate\Http\Request;

class IncomeQuotaController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['fiscal_period_id' => 'required|integer|exists:annual_budgets,id',
            'source' => 'prohibited', 'amount' => 'required|numeric|min:0|max:9999999999999.99']);
        $period = AnnualBudget::findOrFail($data['fiscal_period_id']);
        abort_if($period->closed_at, 422, 'Reopen the fiscal year before changing its quota.');
        $quota = IncomeQuota::updateOrCreate(['annual_budget_id' => $period->id, 'source' => IncomeQuota::OVERALL_SOURCE], ['amount' => $data['amount']]);
        AuditTrail::log($quota, 'modified', $request->user(), 'Income quota saved.', ['changes' => $data]);
        return response()->json(['message' => 'Quota saved.']);
    }
}
