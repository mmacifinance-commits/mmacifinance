<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\IncomeAllocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = (int) ($request->query('year') ?: date('Y'));
        $selectedMonth = $request->query('month') ? (int) $request->query('month') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $departmentId = $request->query('department_id') ? (int) $request->query('department_id') : null;
        $categoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;
        $accountTitleId = $request->query('account_title_id') ? (int) $request->query('account_title_id') : null;

        $availableYears = AnnualBudget::pluck('year')
            ->concat([2024, 2025, 2026, (int) date('Y')])
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if ($startDate && $endDate && strtotime($endDate) < strtotime($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // Expenses Query
        $expQuery = Expense::with(['category', 'particular.department']);
        if ($startDate && $endDate) {
            $expQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $expQuery->whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $selectedMonth);
        } else {
            $expQuery->whereYear('date_encoded', $selectedYear);
        }

        if ($categoryId) {
            $expQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $expQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $expQuery->whereHas('particular', fn($q) => $q->where('department_id', $departmentId));
        }

        // Disbursements Query
        $dsbQuery = Disbursement::with(['expense.category', 'expense.particular.department', 'approvedBy', 'postedBy']);
        if ($startDate && $endDate) {
            $dsbQuery->whereBetween('date_encoded', [$startDate, $endDate]);
        } elseif ($selectedMonth) {
            $dsbQuery->whereYear('date_encoded', $selectedYear)->whereMonth('date_encoded', $selectedMonth);
        } else {
            $dsbQuery->whereYear('date_encoded', $selectedYear);
        }

        if ($departmentId || $categoryId || $accountTitleId) {
            $dsbQuery->whereHas('expense', function ($q) use ($departmentId, $categoryId, $accountTitleId) {
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

        $annualBudgetItemsQuery = BudgetItem::query()
            ->with(['budget', 'category', 'particular.department'])
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));
        if ($categoryId) {
            $annualBudgetItemsQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $annualBudgetItemsQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $annualBudgetItemsQuery->whereHas('particular', fn ($q) => $q->where('department_id', $departmentId));
        }

        $annualBudgetItems = $annualBudgetItemsQuery->get();

        $selectedBudgetItemsQuery = BudgetItem::query()
            ->with(['budget', 'category', 'particular.department'])
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));
        if ($selectedMonth) {
            $selectedBudgetItemsQuery->where('month', $selectedMonth);
        }
        if ($categoryId) {
            $selectedBudgetItemsQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $selectedBudgetItemsQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $selectedBudgetItemsQuery->whereHas('particular', fn ($q) => $q->where('department_id', $departmentId));
        }

        $selectedBudgetItems = $selectedBudgetItemsQuery->get();

        $monthItemsQuery = BudgetItem::query()
            ->whereHas('budget', fn ($q) => $q->where('year', $selectedYear));

        $selectedMonthLabel = 'All Months';
        if ($startDate && $endDate) {
            $startMonth = (int) date('n', strtotime($startDate));
            $endMonth = (int) date('n', strtotime($endDate));
            $monthItemsQuery->whereBetween('month', [$startMonth, $endMonth]);
            $selectedMonthLabel = date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate));
        } elseif ($selectedMonth) {
            $monthItemsQuery->where('month', $selectedMonth);
            $selectedMonthLabel = date('F', mktime(0, 0, 0, $selectedMonth, 1));
        }

        if ($categoryId) {
            $monthItemsQuery->where('category_id', $categoryId);
        }
        if ($accountTitleId) {
            $monthItemsQuery->where('particular_id', $accountTitleId);
        }
        if ($departmentId) {
            $monthItemsQuery->whereHas('particular', fn ($q) => $q->where('department_id', $departmentId));
        }

        $monthItems = $monthItemsQuery->get();
        $monthAppropriation = (float) $monthItems->sum('appropriation');
        $monthExpenditure = (float) $monthItems->sum(fn ($item) => $item->postedExpenditureTotal());

        $selectedMonthPerformance = [
            'month_label' => $selectedMonthLabel,
            'appropriation' => $monthAppropriation,
            'expenditure' => $monthExpenditure,
            'utilizationRate' => $monthAppropriation > 0
                ? round(($monthExpenditure / $monthAppropriation) * 100, 2)
                : 0,
        ];

        $budgetPerformanceByYear = collect($availableYears)->map(function ($year) use ($selectedMonth, $departmentId, $categoryId, $accountTitleId) {
            $query = BudgetItem::query()
                ->whereHas('budget', fn ($q) => $q->where('year', $year))
                ->with(['particular.department']);

            if ($selectedMonth) {
                $query->where('month', $selectedMonth);
            }
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }
            if ($accountTitleId) {
                $query->where('particular_id', $accountTitleId);
            }
            if ($departmentId) {
                $query->whereHas('particular', fn ($q) => $q->where('department_id', $departmentId));
            }

            $items = $query->get();
            $appropriation = (float) $items->sum('appropriation');
            $expenditure = (float) $items->sum(fn ($item) => $item->postedExpenditureTotal());

            return [
                'year' => (int) $year,
                'selectedMonth' => $selectedMonth,
                'appropriation' => $appropriation,
                'expenditure' => $expenditure,
                'utilizationRate' => $appropriation > 0 ? round(($expenditure / $appropriation) * 100, 2) : 0,
            ];
        })->values();

        $selectedMonthLabel = $selectedMonthPerformance['month_label'] ?? ($selectedMonth ? date('F', mktime(0, 0, 0, $selectedMonth, 1)) : 'All Months');

        return Inertia::render('Reports/Index', [
            'budgets' => AnnualBudget::with(['items.category', 'items.particular.department'])->get(),
            'categories' => BudgetCategory::all(),
            'accountTitles' => BudgetParticular::with('category', 'department')->get(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
            'departments' => Department::all(),
            'expenses' => $expQuery->latest()->get(),
            'disbursements' => $dsbQuery->latest()->get(),
            'annualBudgetItems' => $annualBudgetItems,
            'budgetItems' => $selectedBudgetItems,
            'selectedMonthPerformance' => $selectedMonthPerformance,
            'budgetPerformanceByYear' => $budgetPerformanceByYear,
            'selectedMonthLabel' => $selectedMonthLabel,
            'availableYears' => $availableYears,
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'account_title_id' => $accountTitleId,
            ],
        ]);
    }
}
