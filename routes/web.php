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
use Illuminate\Support\Facades\Route;

// --- Guest Routes ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// --- Authenticated Routes ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('budget-categories', BudgetCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('budget-particulars', BudgetParticularController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('annual-budgets', AnnualBudgetController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::post('annual-budgets/{annual_budget}/items', [AnnualBudgetController::class, 'storeItem'])->name('annual-budgets.items.store');
    Route::put('annual-budgets/{annual_budget}/items/{item}', [AnnualBudgetController::class, 'updateItem'])->name('annual-budgets.items.update');
    Route::delete('annual-budgets/{annual_budget}/items/{item}', [AnnualBudgetController::class, 'destroyItem'])->name('annual-budgets.items.destroy');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('disbursements', DisbursementController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});
