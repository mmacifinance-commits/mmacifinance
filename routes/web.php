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
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\PasswordResetController;
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

    // --- Budget section: Super Admin + Budget Officer can CRUD, others can only view ---
    Route::get('/annual-budgets', [AnnualBudgetController::class, 'index'])->name('annual-budgets.index');
    Route::get('/annual-budgets/{annual_budget}', [AnnualBudgetController::class, 'show'])->name('annual-budgets.show');
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

        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);

        Route::post('/budget-categories', [BudgetCategoryController::class, 'store']);
        Route::put('/budget-categories/{budget_category}', [BudgetCategoryController::class, 'update']);
        Route::delete('/budget-categories/{budget_category}', [BudgetCategoryController::class, 'destroy']);

        Route::post('/budget-particulars', [BudgetParticularController::class, 'store']);
        Route::put('/budget-particulars/{budget_particular}', [BudgetParticularController::class, 'update']);
        Route::delete('/budget-particulars/{budget_particular}', [BudgetParticularController::class, 'destroy']);
    });

    // --- Expenditures & Disbursements: Super Admin + Disbursement Officer can CRUD, others view ---
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/disbursements', [DisbursementController::class, 'index'])->name('disbursements.index');

    Route::middleware('role:super_admin,disbursement_officer')->group(function () {
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

        Route::post('/disbursements', [DisbursementController::class, 'store']);
        Route::put('/disbursements/{disbursement}', [DisbursementController::class, 'update']);
        Route::delete('/disbursements/{disbursement}', [DisbursementController::class, 'destroy']);
    });
});
