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
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, BudgetUtilizationService $utilization)
    {
        $budgets = AnnualBudget::query()
            ->latest('year')
            ->get();

        $availableYears = AnnualBudget::pluck('year')
            ->concat([2024, 2025, 2026, (int) date('Y')])
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        // Filters
        $selectedYear = (int) ($request->query('year') ?: (in_array(2026, $availableYears) ? 2026 : ($availableYears[0] ?? date('Y'))));
        $selectedMonth = $request->query('month') ? (int)$request->query('month') : null; // 1-12 or null
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $departmentId = $request->query('department_id') ? (int)$request->query('department_id') : null;
        $categoryId = $request->query('category_id') ? (int)$request->query('category_id') : null;
        $accountTitleId = $request->query('account_title_id') ? (int)$request->query('account_title_id') : null;

        $selectedBudget = $budgets->firstWhere('year', $selectedYear);

        // Calculate appropriations
        $itemsQuery = BudgetItem::query()
            ->with(['budget:id,year', 'category:id,name', 'particular.department'])
            ->whereHas('budget', function ($q) use ($selectedYear) {
                $q->where('year', $selectedYear);
            });

        if ($selectedMonth) {
            $itemsQuery->where('month', $selectedMonth);
        }
        if ($categoryId) {
            $itemsQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $itemsQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $itemsQuery->whereHas('particular', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $budgetItems = $itemsQuery->get();
        BudgetItem::hydrateDerivedTotals($budgetItems);

        $totalAppropriation = (float) $budgetItems->sum('appropriation');
        $annualAppropriation = $selectedBudget
            ? (float) BudgetItem::where('budget_id', $selectedBudget->id)->sum('appropriation')
            : $totalAppropriation;

        // Posted Disbursements Query (Only POSTED affect expenditures & utilization)
        $disbQuery = $utilization->queryForBudgetFilters(
            $selectedYear,
            $selectedMonth,
            $startDate,
            $endDate,
            $departmentId,
            $categoryId,
            $accountTitleId
        )->with('expense.budgetItem');

        $postedDisbursements = $disbQuery->get();
        $totalExpenditure = (float) $postedDisbursements->sum('amount');

        // Official expenditures are derived only from posted disbursements.
        $effectiveExpenditure = $totalExpenditure;

        $remainingBalance = $totalAppropriation - $effectiveExpenditure;
        $utilizationRate = $totalAppropriation > 0 ? round(($effectiveExpenditure / $totalAppropriation) * 100, 2) : 0;

        // Category breakdown statistics
        $categories = BudgetCategory::all();
        $categoryStats = [];
        foreach ($categories as $cat) {
            $catAppr = (float) $budgetItems->where('category_id', $cat->id)->sum('appropriation');
            $catExp = (float) $postedDisbursements->filter(function ($d) use ($cat) {
                return $d->expense?->budgetItem?->category_id == $cat->id;
            })->sum('amount');

            if ($catAppr > 0 || $catExp > 0) {
                $categoryStats[] = [
                    'name' => $cat->name,
                    'appropriation' => $catAppr,
                    'expenditure' => $catExp,
                    'utilization' => $catAppr > 0 ? round($catExp / $catAppr * 100, 1) : 0,
                ];
            }
        }

        // Multi-Year Comparison Data for Dynamic Graph (2024, 2025, 2026, etc.)
        $compareYears = array_slice($availableYears, 0, 4); // top 4 recent years
        $yearAppropriationsQuery = BudgetItem::query()
            ->join('annual_budgets', 'budget_items.budget_id', '=', 'annual_budgets.id')
            ->whereIn('annual_budgets.year', $compareYears);
        if ($categoryId) { $yearAppropriationsQuery->where('budget_items.category_id', $categoryId); }
        if ($accountTitleId) { $yearAppropriationsQuery->where('budget_items.particular_id', $accountTitleId); }
        if ($departmentId) {
            $yearAppropriationsQuery->whereExists(function ($query) use ($departmentId) {
                $query->selectRaw('1')->from('budget_particulars')
                    ->whereColumn('budget_particulars.id', 'budget_items.particular_id')
                    ->where('budget_particulars.department_id', $departmentId);
            });
        }
        $yearAppropriations = $yearAppropriationsQuery
            ->selectRaw('annual_budgets.year, SUM(budget_items.appropriation) as total')
            ->groupBy('annual_budgets.year')
            ->pluck('total', 'year');

        $yearExpenditures = $utilization->totalsByBudgetYear(
            $compareYears,
            $departmentId,
            $categoryId,
            $accountTitleId
        );

        $multiYearComparison = collect($compareYears)->map(function ($y) use ($yearAppropriations, $yearExpenditures) {
            $yAppr = (float) ($yearAppropriations[$y] ?? 0);
            $yExp = (float) ($yearExpenditures[$y] ?? 0);

            return [
                'year' => (int) $y,
                'appropriation' => $yAppr,
                'expenditure' => $yExp,
                'balance' => $yAppr - $yExp,
                'utilization' => $yAppr > 0 ? round(($yExp / $yAppr) * 100, 1) : 0,
            ];
        })->sortBy('year')->values()->all();

        // Monthly Breakdown for Selected Year (Jan - Dec)
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyAppropriationsQuery = BudgetItem::query()
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));
        if ($categoryId) { $monthlyAppropriationsQuery->where('category_id', $categoryId); }
        if ($accountTitleId) { $monthlyAppropriationsQuery->where('particular_id', $accountTitleId); }
        if ($departmentId) { $monthlyAppropriationsQuery->whereHas('particular', fn ($q) => $q->where('department_id', $departmentId)); }
        $monthlyAppropriations = $monthlyAppropriationsQuery
            ->selectRaw('month, SUM(appropriation) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $monthlyExpenditures = $utilization->totalsByAllocationMonth(
            $selectedYear,
            $startDate,
            $endDate,
            $departmentId,
            $categoryId,
            $accountTitleId
        );

        $monthlyBreakdown = collect(range(1, 12))->map(function ($m) use ($months, $monthlyAppropriations, $monthlyExpenditures) {
            $mAppr = (float) ($monthlyAppropriations[$m] ?? 0);
            $mExp = (float) ($monthlyExpenditures[$m] ?? 0);

            return [
                'month' => $months[$m - 1],
                'month_num' => $m,
                'appropriation' => $mAppr,
                'expenditure' => $mExp,
                'balance' => $mAppr - $mExp,
                'utilization' => $mAppr > 0 ? round(($mExp / $mAppr) * 100, 1) : 0,
            ];
        })->all();

        // Recent Posted Transactions
        $recentDisbursements = (clone $disbQuery)
            ->latest('date_encoded')
            ->take(6)
            ->get();

        return Inertia::render('Dashboard', [
            'budgets' => $budgets,
            'availableYears' => $availableYears,
            'departments' => Department::all(),
            'categories' => $categories,
            'accountTitles' => BudgetParticular::all(),
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'account_title_id' => $accountTitleId,
            ],
            'stats' => [
                'annualBudget' => $annualAppropriation,
                'totalAppropriation' => $totalAppropriation,
                'totalExpenditure' => $effectiveExpenditure,
                'balance' => $remainingBalance,
                'utilizationRate' => $utilizationRate,
                'totalTransactions' => $postedDisbursements->count(),
                'pendingExpenses' => Expense::whereYear('date_encoded', $selectedYear)->where('status', 'pending')->count(),
                'pendingDisbursements' => Disbursement::whereYear('date_encoded', $selectedYear)->whereIn('status', ['draft', 'for_release', 'for_approval'])->count(),
            ],
            'categoryStats' => $categoryStats,
            'multiYearComparison' => $multiYearComparison,
            'monthlyBreakdown' => $monthlyBreakdown,
            'recentDisbursements' => $recentDisbursements,
            'tutorial' => [
                'show' => $request->user()?->shouldShowTutorial('dashboard') ?? false,
                'state' => [
                    'status' => $request->user()?->tutorial_status ?? 'pending',
                    'version' => $request->user()?->tutorial_version,
                    'current_step' => $request->user()?->tutorial_current_step,
                    'completed_at' => $request->user()?->tutorial_completed_at?->toISOString(),
                    'skipped_at' => $request->user()?->tutorial_skipped_at?->toISOString(),
                ],
            ],
        ]);
    }
}
