<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
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

        $availableYears = $budgets->pluck('year')->unique()->values();

        $selectedYear = $request->query('year');
        if (!$selectedYear) {
            if ($availableYears->contains(2026)) {
                $selectedYear = 2026;
            } elseif ($budgets->isNotEmpty()) {
                $selectedYear = $budgets->first()->year;
            }
        }

        $selectedBudget = $budgets->firstWhere('year', $selectedYear);

        $totalAppropriation = 0;
        $totalExpenditure = 0;
        $categoryStats = [];

        // Fetch non-cancelled expenses and disbursements for the selected fiscal year
        $expensesForYear = Expense::whereYear('date_encoded', $selectedYear)
            ->where('status', '!=', 'cancelled')
            ->get();

        $disbursementsForYear = Disbursement::whereYear('date_encoded', $selectedYear)
            ->where('status', '!=', 'cancelled')
            ->get();

        $totalYearExpenses = (float) $expensesForYear->sum('amount');
        $totalYearDisbursements = (float) $disbursementsForYear->sum('amount');

        if ($selectedBudget) {
            $totalAppropriation = (float) $selectedBudget->items->sum('appropriation');

            $grouped = $selectedBudget->items->groupBy(fn($item) => $item->category?->name ?? 'Uncategorized');

            foreach ($grouped as $catName => $items) {
                $catAppr = (float) $items->sum('appropriation');

                // Compute expenditure for items in this category
                $catExp = 0;
                foreach ($items as $item) {
                    $actualExp = $expensesForYear->where('particular_id', $item->particular_id)->sum('amount');
                    $itemExp = max((float) $actualExp, (float) $item->expenditure);
                    $catExp += $itemExp;
                }

                // Check if any expenses match by category_id directly
                $catId = $items->first()->category_id;
                if ($catId) {
                    $catActualExp = (float) $expensesForYear->where('category_id', $catId)->sum('amount');
                    if ($catActualExp > $catExp) {
                        $catExp = $catActualExp;
                    }
                }

                // Check if any disbursements match this category name in source
                $dsbSourceExp = (float) $disbursementsForYear->filter(function ($d) use ($catName) {
                    return strtolower(trim($d->source)) === strtolower(trim($catName));
                })->sum('amount');

                $catExp += $dsbSourceExp;

                $categoryStats[] = [
                    'name' => $catName,
                    'appropriation' => $catAppr,
                    'expenditure' => $catExp,
                    'utilization' => $catAppr > 0 ? round($catExp / $catAppr * 100, 1) : 0,
                ];
            }

            $sumCatExp = array_sum(array_column($categoryStats, 'expenditure'));
            $totalExpenditure = max($sumCatExp, $totalYearExpenses + $totalYearDisbursements);
        } else {
            $totalExpenditure = $totalYearExpenses + $totalYearDisbursements;
        }

        // Recent expenses for the selected fiscal year (last 5)
        $recentExpenses = Expense::with('category')
            ->whereYear('date_encoded', $selectedYear)
            ->latest('date_encoded')
            ->take(5)
            ->get();

        // Recent disbursements for the selected fiscal year (last 5)
        $recentDisbursements = Disbursement::whereYear('date_encoded', $selectedYear)
            ->latest('date_encoded')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'budgets' => $budgets,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear ? (int)$selectedYear : null,
            'stats' => [
                'totalAppropriation' => (float) $totalAppropriation,
                'totalExpenditure' => (float) $totalExpenditure,
                'balance' => (float) ($totalAppropriation - $totalExpenditure),
                'pendingExpenses' => Expense::whereYear('date_encoded', $selectedYear)->where('status', 'pending')->count(),
                'pendingDisbursements' => Disbursement::whereYear('date_encoded', $selectedYear)->where('status', 'pending')->count(),
            ],
            'categoryStats' => $categoryStats,
            'recentExpenses' => $recentExpenses,
            'recentDisbursements' => $recentDisbursements,
            'latestYear' => $selectedYear ? (int)$selectedYear : null,
        ]);
    }
}

