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

        if ($selectedBudget) {
            $totalAppropriation = $selectedBudget->items->sum('appropriation');
            $totalExpenditure = $selectedBudget->items->sum('expenditure');

            $grouped = $selectedBudget->items->groupBy(fn($item) => $item->category?->name ?? 'Uncategorized');
            foreach ($grouped as $catName => $items) {
                $catAppr = $items->sum('appropriation');
                $catExp = $items->sum('expenditure');
                $categoryStats[] = [
                    'name' => $catName,
                    'appropriation' => $catAppr,
                    'expenditure' => $catExp,
                    'utilization' => $catAppr > 0 ? round($catExp / $catAppr * 100, 1) : 0,
                ];
            }
        }

        // Recent expenses (last 5)
        $recentExpenses = Expense::with('category')
            ->latest('date_encoded')
            ->take(5)
            ->get();

        // Recent disbursements (last 5)
        $recentDisbursements = Disbursement::latest('date_encoded')
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
                'pendingExpenses' => Expense::where('status', 'pending')->count(),
                'pendingDisbursements' => Disbursement::where('status', 'pending')->count(),
            ],
            'categoryStats' => $categoryStats,
            'recentExpenses' => $recentExpenses,
            'recentDisbursements' => $recentDisbursements,
            'latestYear' => $selectedYear ? (int)$selectedYear : null,
        ]);
    }
}

