<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\BudgetCategoryController;
use App\Http\Controllers\BudgetParticularController;
use App\Http\Controllers\AnnualBudgetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\TutorialController;
use Illuminate\Support\Facades\Route;

// --- Guest Routes ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/2fa/verify', [TwoFactorController::class, 'index'])->name('2fa.index');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify');
    Route::post('/2fa/resend', [TwoFactorController::class, 'resend'])->name('2fa.resend');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetCode'])->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

// --- Authenticated Routes ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Everyone can view the dashboard and reports
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/income', [IncomeController::class, 'index'])->name('income.index');
    Route::get('/iaeo', [RevenueController::class, 'index'])->name('iaeo.index');
    Route::redirect('/revenue', '/iaeo', 301);
    Route::get('/tutorial/state', [TutorialController::class, 'state'])->name('tutorial.state');
    Route::post('/tutorial/step', [TutorialController::class, 'step'])->name('tutorial.step');
    Route::post('/tutorial/skip', [TutorialController::class, 'skip'])->name('tutorial.skip');
    Route::post('/tutorial/complete', [TutorialController::class, 'complete'])->name('tutorial.complete');

    // --- Budget section: Super Admin + Budget Officer can CRUD, others can only view ---
    Route::get('/annual-budgets', [AnnualBudgetController::class, 'index'])->name('annual-budgets.index');
    Route::get('/annual-budgets/{annual_budget}', [AnnualBudgetController::class, 'show'])->name('annual-budgets.show');
    Route::get('/annual-budgets/{annual_budget}/export-csv', [AnnualBudgetController::class, 'exportCsv'])->name('annual-budgets.export-csv');
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::get('/budget-categories', [BudgetCategoryController::class, 'index'])->name('budget-categories.index');
    Route::get('/budget-particulars', [BudgetParticularController::class, 'index'])->name('budget-particulars.index');

    // Budget write operations — Super Admin + Budget Officer only
    Route::middleware('role:super_admin,budget_officer')->group(function () {
        Route::post('/annual-budgets', [AnnualBudgetController::class, 'store'])->name('annual-budgets.store');
        Route::delete('/annual-budgets/{annual_budget}', [AnnualBudgetController::class, 'destroy'])->name('annual-budgets.destroy');
        Route::post('annual-budgets/{annual_budget}/items', [AnnualBudgetController::class, 'storeItem'])->name('annual-budgets.items.store');
        Route::put('annual-budgets/{annual_budget}/items/{item}', [AnnualBudgetController::class, 'updateItem'])->name('annual-budgets.items.update');
        Route::delete('annual-budgets/{annual_budget}/items/{item}', [AnnualBudgetController::class, 'destroyItem'])->name('annual-budgets.items.destroy');
        Route::post('annual-budgets/{annual_budget}/import-csv', [AnnualBudgetController::class, 'importCsv'])->name('annual-budgets.import-csv');

        Route::post('/budget-categories', [BudgetCategoryController::class, 'store']);
        Route::put('/budget-categories/{budget_category}', [BudgetCategoryController::class, 'update']);
        Route::delete('/budget-categories/{budget_category}', [BudgetCategoryController::class, 'destroy']);

        Route::post('/budget-particulars', [BudgetParticularController::class, 'store']);
        Route::put('/budget-particulars/{budget_particular}', [BudgetParticularController::class, 'update']);
        Route::delete('/budget-particulars/{budget_particular}', [BudgetParticularController::class, 'destroy']);
    });

    // Responsibility centers are organization-wide setup data. Head of Finance only.
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });

    // --- Expenditures & Disbursements: Super Admin + Disbursement Officer + Cashier can view/CRUD disbursements ---
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/disbursements', [DisbursementController::class, 'index'])->name('disbursements.index');

    Route::middleware('role:super_admin,disbursement_officer,cashier')->group(function () {
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
    });

    Route::middleware('role:super_admin,disbursement_officer,cashier')->group(function () {
        Route::post('/expenses/{expense}/submit', [ExpenseController::class, 'submitForApproval'])->name('expenses.submit');
    });

    // Cashier, Disbursement Officer, and Super Admin can manage disbursements & submit for approval
    Route::middleware('role:super_admin,disbursement_officer,cashier')->group(function () {
        Route::post('/disbursements', [DisbursementController::class, 'store']);
        Route::put('/disbursements/{disbursement}', [DisbursementController::class, 'update']);
        Route::delete('/disbursements/{disbursement}', [DisbursementController::class, 'destroy']);
        Route::post('/disbursements/{disbursement}/submit', [DisbursementController::class, 'submitForApproval'])->name('disbursements.submit');
    });

    // Income management: Head Admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/income', [IncomeController::class, 'store'])->name('income.store');
        Route::put('/income/{income}', [IncomeController::class, 'update'])->name('income.update');
        Route::delete('/income/{income}', [IncomeController::class, 'destroy'])->name('income.destroy');
    });

    // Head of Finance (Super Admin) approval & posting operations
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/disbursements/{disbursement}/approve', [DisbursementController::class, 'approve'])->name('disbursements.approve');
        Route::post('/disbursements/{disbursement}/post', [DisbursementController::class, 'postDisbursement'])->name('disbursements.post');
        Route::post('/disbursements/{disbursement}/reject', [DisbursementController::class, 'reject'])->name('disbursements.reject');
        Route::post('/disbursements/{disbursement}/return', [DisbursementController::class, 'returnForRevision'])->name('disbursements.return');
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
        Route::post('/expenses/{expense}/return', [ExpenseController::class, 'returnForRevision'])->name('expenses.return');
    });
});
