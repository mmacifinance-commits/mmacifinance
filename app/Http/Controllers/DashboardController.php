<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Services\BudgetUtilizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, BudgetUtilizationService $utilization)
    {
        $budgets = AnnualBudget::query()->orderByDesc('start_date')->get();
        $selectedBudget = $this->selectedBudget($request, $budgets);
        $allocationMonth = $this->selectedAllocationMonth($request, $selectedBudget);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $departmentId = $request->integer('department_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;
        $accountTitleId = $request->integer('account_title_id') ?: null;

        $itemsQuery = BudgetItem::query()
            ->with(['budget:id,year,start_date,end_date', 'category:id,name', 'particular.department'])
            ->when($selectedBudget, fn ($query) => $query->where('budget_id', $selectedBudget->id))
            ->when(! $selectedBudget, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($allocationMonth, fn ($query) => $query->whereDate('allocation_month', $allocationMonth))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($accountTitleId, fn ($query) => $query->where('particular_id', $accountTitleId))
            ->when($departmentId, fn ($query) => $query->whereHas(
                'particular',
                fn ($particularQuery) => $particularQuery->where('department_id', $departmentId)
            ));

        $budgetItems = $itemsQuery->get();
        BudgetItem::hydrateDerivedTotals($budgetItems);
        $totalAppropriation = (float) $budgetItems->sum('appropriation');
        $annualAppropriation = $selectedBudget
            ? (float) BudgetItem::where('budget_id', $selectedBudget->id)->sum('appropriation')
            : 0.0;

        $disbQuery = $selectedBudget
            ? $utilization->queryForAnnualBudgetFilters(
                $selectedBudget,
                $allocationMonth,
                $startDate,
                $endDate,
                $departmentId,
                $categoryId,
                $accountTitleId
            )->with('expense.budgetItem')
            : Disbursement::query()->whereRaw('1 = 0');

        $postedDisbursements = $disbQuery->get();
        $totalExpenditure = (float) $postedDisbursements->sum('amount');
        $remainingBalance = $totalAppropriation - $totalExpenditure;
        $utilizationRate = $totalAppropriation > 0
            ? round(($totalExpenditure / $totalAppropriation) * 100, 2)
            : 0;

        $categories = BudgetCategory::all();
        $categoryStats = $categories->map(function ($category) use ($budgetItems, $postedDisbursements) {
            $appropriation = (float) $budgetItems->where('category_id', $category->id)->sum('appropriation');
            $expenditure = (float) $postedDisbursements->filter(
                fn ($disbursement) => (int) ($disbursement->expense?->budgetItem?->category_id ?? 0) === (int) $category->id
            )->sum('amount');

            if ($appropriation <= 0 && $expenditure <= 0) {
                return null;
            }

            return [
                'name' => $category->name,
                'appropriation' => $appropriation,
                'expenditure' => $expenditure,
                'utilization' => $appropriation > 0 ? round($expenditure / $appropriation * 100, 1) : 0,
            ];
        })->filter()->values()->all();

        $multiYearComparison = $budgets->take(4)->sortBy('start_date')->values()
            ->map(function (AnnualBudget $budget) use ($utilization, $departmentId, $categoryId, $accountTitleId) {
                $appropriationQuery = BudgetItem::query()->where('budget_id', $budget->id)
                    ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                    ->when($accountTitleId, fn ($query) => $query->where('particular_id', $accountTitleId))
                    ->when($departmentId, fn ($query) => $query->whereHas(
                        'particular',
                        fn ($particularQuery) => $particularQuery->where('department_id', $departmentId)
                    ));
                $appropriation = (float) $appropriationQuery->sum('appropriation');
                $expenditure = (float) $utilization->queryForAnnualBudgetFilters(
                    $budget,
                    null,
                    null,
                    null,
                    $departmentId,
                    $categoryId,
                    $accountTitleId
                )->sum('amount');

                return [
                    'year' => $budget->year,
                    'label' => $budget->fiscal_year_label,
                    'appropriation' => $appropriation,
                    'expenditure' => $expenditure,
                    'balance' => $appropriation - $expenditure,
                    'utilization' => $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 1) : 0,
                ];
            })->all();

        $monthlyAppropriations = $selectedBudget
            ? BudgetItem::query()->where('budget_id', $selectedBudget->id)
                ->when($allocationMonth, fn ($query) => $query->whereDate('allocation_month', $allocationMonth))
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->when($accountTitleId, fn ($query) => $query->where('particular_id', $accountTitleId))
                ->when($departmentId, fn ($query) => $query->whereHas(
                    'particular',
                    fn ($particularQuery) => $particularQuery->where('department_id', $departmentId)
                ))
                ->selectRaw('allocation_month, SUM(appropriation) as total')
                ->groupBy('allocation_month')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    \Carbon\Carbon::parse($row->allocation_month)->toDateString() => (float) $row->total,
                ])
            : collect();

        $monthlyExpenditures = $selectedBudget
            ? $utilization->totalsByFiscalAllocationMonth(
                $selectedBudget,
                $startDate,
                $endDate,
                $departmentId,
                $categoryId,
                $accountTitleId
            )
            : collect();
        if ($allocationMonth) {
            $monthlyExpenditures = $monthlyExpenditures->only($allocationMonth);
        }

        $monthlyBreakdown = collect($selectedBudget?->fiscal_months ?? [])->map(function ($period) use ($monthlyAppropriations, $monthlyExpenditures) {
            $key = $period['value'];
            $appropriation = (float) ($monthlyAppropriations[$key] ?? 0);
            $expenditure = (float) ($monthlyExpenditures[$key] ?? 0);

            return [
                'month' => $period['short_label'],
                'month_num' => $period['month'],
                'allocation_month' => $key,
                'appropriation' => $appropriation,
                'expenditure' => $expenditure,
                'balance' => $appropriation - $expenditure,
                'utilization' => $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 1) : 0,
            ];
        })->all();

        $pendingExpenses = $selectedBudget
            ? Expense::whereHas('budgetItem', fn ($query) => $query->where('budget_id', $selectedBudget->id))
                ->where('status', 'pending')->count()
            : 0;
        $pendingDisbursements = $selectedBudget
            ? Disbursement::whereHas('expense.budgetItem', fn ($query) => $query->where('budget_id', $selectedBudget->id))
                ->whereIn('status', ['draft', 'for_release', 'for_approval'])->count()
            : 0;

        return Inertia::render('Dashboard', [
            'budgets' => $budgets,
            'fiscalPeriods' => $budgets,
            'availableYears' => $budgets->pluck('year')->all(),
            'departments' => Department::all(),
            'categories' => $categories,
            'accountTitles' => BudgetParticular::all(),
            'filters' => [
                'fiscal_period_id' => $selectedBudget?->id,
                'year' => $selectedBudget?->year,
                'allocation_month' => $allocationMonth,
                'month' => $allocationMonth ? (int) date('n', strtotime($allocationMonth)) : null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'account_title_id' => $accountTitleId,
            ],
            'stats' => [
                'annualBudget' => $annualAppropriation,
                'totalAppropriation' => $totalAppropriation,
                'totalExpenditure' => $totalExpenditure,
                'balance' => $remainingBalance,
                'utilizationRate' => $utilizationRate,
                'totalTransactions' => $postedDisbursements->count(),
                'pendingExpenses' => $pendingExpenses,
                'pendingDisbursements' => $pendingDisbursements,
            ],
            'categoryStats' => $categoryStats,
            'multiYearComparison' => $multiYearComparison,
            'monthlyBreakdown' => $monthlyBreakdown,
            'recentDisbursements' => (clone $disbQuery)->latest('date_encoded')->take(6)->get(),
        ]);
    }

    private function selectedBudget(Request $request, Collection $budgets): ?AnnualBudget
    {
        $id = $request->integer('fiscal_period_id');
        if ($id) {
            return $budgets->firstWhere('id', $id);
        }

        $year = $request->integer('year');

        return ($year ? $budgets->firstWhere('year', $year) : null) ?? $budgets->first();
    }

    private function selectedAllocationMonth(Request $request, ?AnnualBudget $budget): ?string
    {
        if (! $budget) {
            return null;
        }

        if ($request->filled('allocation_month')) {
            $value = date('Y-m-01', strtotime((string) $request->query('allocation_month')));

            return collect($budget->fiscal_months)->contains('value', $value) ? $value : null;
        }

        if ($request->filled('month')) {
            return $budget->allocationMonthForNumber((int) $request->query('month'))?->toDateString();
        }

        return null;
    }
}
