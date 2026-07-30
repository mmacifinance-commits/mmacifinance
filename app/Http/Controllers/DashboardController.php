<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $budgets = AnnualBudget::with('items.category', 'items.particular.department')
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

        $totalAppropriation = (float) $budgetItems->sum('appropriation');
        $annualAppropriation = $selectedBudget ? (float) $selectedBudget->items->sum('appropriation') : $totalAppropriation;

        // Posted Disbursements Query (Only POSTED affect expenditures & utilization)
        $disbQuery = Disbursement::with('expense')
            ->where('status', 'posted');

        if ($startDate && $endDate) {
            $disbQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $disbQuery->whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $selectedMonth);
        } else {
            $disbQuery->whereYear('date_encoded', $selectedYear);
        }

        if ($departmentId || $categoryId || $accountTitleId) {
            $disbQuery->whereHas('expense', function ($q) use ($departmentId, $categoryId, $accountTitleId) {
                if ($departmentId) {
                    $q->whereHas('particular', fn($p) => $p->where('department_id', $departmentId));
                }
                if ($categoryId) {
                    $q->where('category_id', $categoryId);
                }
                if ($accountTitleId) {
                    $q->where('particular_id', $accountTitleId);
                }
            });
        }

        $postedDisbursements = $disbQuery->get();
        $totalExpenditure = (float) $postedDisbursements->sum('amount');

        // Also check posted expenses directly if any
        $expenseQuery = Expense::where('paid', '>', 0);
        if ($startDate && $endDate) {
            $expenseQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $expenseQuery->whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $selectedMonth);
        } else {
            $expenseQuery->whereYear('date_encoded', $selectedYear);
        }
        if ($categoryId) {
            $expenseQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $expenseQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $expenseQuery->whereHas('particular', fn($p) => $p->where('department_id', $departmentId));
        }

        $postedExpensesTotal = (float) $expenseQuery->sum('paid');
        $effectiveExpenditure = max($totalExpenditure, $postedExpensesTotal);

        $remainingBalance = $totalAppropriation - $effectiveExpenditure;
        $utilizationRate = $totalAppropriation > 0 ? round(($effectiveExpenditure / $totalAppropriation) * 100, 2) : 0;

        // Category breakdown statistics
        $categories = BudgetCategory::all();
        $categoryStats = [];
        foreach ($categories as $cat) {
            $catAppr = (float) $budgetItems->where('category_id', $cat->id)->sum('appropriation');
            $catExp = (float) $postedDisbursements->filter(function ($d) use ($cat) {
                return $d->expense?->category_id == $cat->id || strtolower(trim($d->source)) === strtolower(trim($cat->name));
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
        $multiYearComparison = [];
        $compareYears = array_slice($availableYears, 0, 4); // top 4 recent years
        foreach ($compareYears as $y) {
            $yBudget = AnnualBudget::where('year', $y)->first();
            
            $yApprQuery = BudgetItem::whereHas('budget', fn($q) => $q->where('year', $y));
            if ($categoryId) { $yApprQuery->where('category_id', $categoryId); }
            if ($accountTitleId) { $yApprQuery->where('particular_id', $accountTitleId); }
            if ($departmentId) { $yApprQuery->whereHas('particular', fn($q) => $q->where('department_id', $departmentId)); }
            $yAppr = $yBudget ? (float) $yApprQuery->sum('appropriation') : 0;

            $yExpQuery = Disbursement::whereYear('date_encoded', $y)->where('status', 'posted');
            if ($departmentId || $categoryId || $accountTitleId) {
                $yExpQuery->whereHas('expense', function ($q) use ($departmentId, $categoryId, $accountTitleId) {
                    if ($departmentId) { $q->whereHas('particular', fn($p) => $p->where('department_id', $departmentId)); }
                    if ($categoryId) { $q->where('category_id', $categoryId); }
                    if ($accountTitleId) { $q->where('particular_id', $accountTitleId); }
                });
            }
            $yExp = (float) $yExpQuery->sum('amount');

            $multiYearComparison[] = [
                'year' => (int) $y,
                'appropriation' => $yAppr,
                'expenditure' => $yExp,
                'balance' => $yAppr - $yExp,
                'utilization' => $yAppr > 0 ? round(($yExp / $yAppr) * 100, 1) : 0,
            ];
        }
        usort($multiYearComparison, fn($a, $b) => $a['year'] <=> $b['year']);

        // Monthly Breakdown for Selected Year (Jan - Dec)
        $monthlyBreakdown = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($m = 1; $m <= 12; $m++) {
            $mApprQuery = BudgetItem::whereHas('budget', fn($q) => $q->where('year', $selectedYear))->where('month', $m);
            if ($categoryId) { $mApprQuery->where('category_id', $categoryId); }
            if ($accountTitleId) { $mApprQuery->where('particular_id', $accountTitleId); }
            if ($departmentId) { $mApprQuery->whereHas('particular', fn($q) => $q->where('department_id', $departmentId)); }
            $mAppr = (float) $mApprQuery->sum('appropriation');

            $mExpQuery = Disbursement::whereYear('date_encoded', $selectedYear)
                ->whereMonth('date_encoded', $m)
                ->where('status', 'posted');
            if ($departmentId || $categoryId || $accountTitleId) {
                $mExpQuery->whereHas('expense', function ($q) use ($departmentId, $categoryId, $accountTitleId) {
                    if ($departmentId) { $q->whereHas('particular', fn($p) => $p->where('department_id', $departmentId)); }
                    if ($categoryId) { $q->where('category_id', $categoryId); }
                    if ($accountTitleId) { $q->where('particular_id', $accountTitleId); }
                });
            }
            $mExp = (float) $mExpQuery->sum('amount');

            $monthlyBreakdown[] = [
                'month' => $months[$m - 1],
                'month_num' => $m,
                'appropriation' => $mAppr,
                'expenditure' => $mExp,
                'balance' => $mAppr - $mExp,
                'utilization' => $mAppr > 0 ? round(($mExp / $mAppr) * 100, 1) : 0,
            ];
        }

        // Recent Posted Transactions
        $recentDisbursements = Disbursement::with('expense')
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
                'pendingExpenses' => Expense::where('status', 'pending')->count(),
                'pendingDisbursements' => Disbursement::whereIn('status', ['draft', 'for_release', 'for_approval'])->count(),
            ],
            'categoryStats' => $categoryStats,
            'multiYearComparison' => $multiYearComparison,
            'monthlyBreakdown' => $monthlyBreakdown,
            'recentDisbursements' => $recentDisbursements,
        ]);
    }
}