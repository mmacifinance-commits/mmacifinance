<?php

namespace App\Http\Controllers;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
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

        return Inertia::render('Reports/Index', [
            'budgets' => AnnualBudget::with(['items.category', 'items.particular.department'])->get(),
            'categories' => BudgetCategory::all(),
            'accountTitles' => BudgetParticular::with('category', 'department')->get(),
            'particulars' => BudgetParticular::with('category', 'department')->get(),
            'departments' => Department::all(),
            'expenses' => $expQuery->latest()->get(),
            'disbursements' => $dsbQuery->latest()->get(),
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
