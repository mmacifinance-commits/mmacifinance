<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\IncomeQuota;
use App\Services\FiscalPeriodService;
use Illuminate\Http\Request;

class IncomeBreakdownController extends Controller
{
    public function __invoke(Request $request, FiscalPeriodService $periods)
    {
        $data = $request->validate([
            'details' => 'nullable|boolean',
            'category_id' => 'nullable|integer|exists:budget_categories,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'fiscal_period_id' => 'nullable|integer|exists:annual_budgets,id',
            'allocation_month' => 'nullable|date_format:Y-m-d',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $period = $periods->resolve($data['fiscal_period_id'] ?? null, null);
        $query = Income::query()->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('incomes.department_id', $id))->when($period, fn ($q) => $q->whereBetween('date_encoded', [
            $period->fiscalStart()->toDateString(), $period->fiscalEnd()->toDateString(),
        ]));
        if (!empty($data['allocation_month'])) {
            $month = \Carbon\Carbon::parse($data['allocation_month'])->startOfMonth();
            $query->whereBetween('date_encoded', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
        }
        $query->when($data['start_date'] ?? null, fn ($q, $date) => $q->whereDate('date_encoded', '>=', $date))
            ->when($data['end_date'] ?? null, fn ($q, $date) => $q->whereDate('date_encoded', '<=', $date));
        if ($data['details'] ?? false) {
            $detailRows = (clone $query)->where('incomes.budget_category_id', $data['category_id'] ?? null)
                ->selectRaw("LOWER(TRIM(source)) as source_key, MIN(TRIM(source)) as source,
                    SUM(CASE WHEN receipt_no IS NULL OR receipt_no = '' THEN amount ELSE 0 END) as projected,
                    SUM(CASE WHEN receipt_no IS NOT NULL AND receipt_no <> '' THEN amount ELSE 0 END) as actual")
                ->groupByRaw('LOWER(TRIM(source))')->orderBy('source_key')->toBase()->get()
                ->map(fn ($row) => ['source' => $row->source, 'projected' => round((float) $row->projected, 2),
                    'actual' => round((float) $row->actual, 2),
                    'difference' => round((float) $row->projected - (float) $row->actual, 2),
                    'collection_rate' => $row->projected > 0 ? round($row->actual / $row->projected * 100, 2) : null]);
            return response()->json(['rows' => $detailRows]);
        }
        $rows = $query->leftJoin('budget_categories', 'incomes.budget_category_id', '=', 'budget_categories.id')
            ->selectRaw("budget_categories.id as category_id, COALESCE(budget_categories.name, 'Uncategorized') as source,
            SUM(CASE WHEN receipt_no IS NULL OR receipt_no = '' THEN amount ELSE 0 END) as projected,
            SUM(CASE WHEN receipt_no IS NOT NULL AND receipt_no <> '' THEN amount ELSE 0 END) as actual")
            ->groupBy('budget_categories.id', 'budget_categories.name')->orderBy('budget_categories.name')->toBase()->get()
            ->map(fn ($row) => ['category_id' => $row->category_id, 'source' => $row->source, 'projected' => round((float) $row->projected, 2),
                'actual' => round((float) $row->actual, 2), 'difference' => round((float) $row->projected - (float) $row->actual, 2)]);
        $quota = IncomeQuota::where('annual_budget_id', $period?->id ?? 0)
            ->where('source', IncomeQuota::OVERALL_SOURCE)->value('amount');
        $quota = $quota !== null ? (float) $quota : null;
        $rows = $rows->map(fn ($row) => $row + ['collection_rate' => $row['projected'] > 0
            ? round($row['actual'] / $row['projected'] * 100, 2) : null])->values();
        $projected = round((float) $rows->sum('projected'), 2);
        $actual = round((float) $rows->sum('actual'), 2);
        return response()->json(['departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']), 'rows' => $rows, 'overall' => [
            'projected' => $projected, 'actual' => $actual, 'quota' => $quota,
            'collection_rate' => $projected > 0 ? round($actual / $projected * 100, 2) : null,
            'quota_progress' => $quota > 0 ? round($actual / $quota * 100, 2) : null,
            'quota_remaining' => $quota !== null ? round(max(0, $quota - $actual), 2) : null,
        ]]);
    }
}
