<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'));

Route::middleware('guest')->prefix('auth')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');

    Route::middleware('throttle:auth')->group(function () {
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->name('password.code');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
    });
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::view('/dashboard', 'dashboard')->middleware('can:dashboard.view')->name('dashboard');
    Route::view('/sales', 'sales.index')->middleware('can:sales.view')->name('sales.index');
    Route::view('/jobs', 'jobs.index')->middleware('can:jobs.view')->name('jobs.index');
    Route::view('/services', 'services.index')->middleware('can:services.view')->name('services.index');

    Route::view('/bills', 'bills.index')->middleware('can:bills.view')->name('bills.index');
    Route::view('/quotations', 'quotations.index')->middleware('can:quotations.view')->name('quotations.index');
    Route::view('/warranty', 'warranty.index')->middleware('can:warranty.view')->name('warranty.index');

    Route::view('/products', 'products.index')->middleware('can:products.view')->name('products.index');
    Route::view('/grn', 'grn.index')->middleware('can:grn.view')->name('grn.index');
    Route::view('/stock-transfer', 'stock-transfer.index')->middleware('can:stockTransfer.view')->name('stock-transfer.index');
    Route::view('/stock-out', 'stock-out.index')->middleware('can:stockOut.view')->name('stock-out.index');
    Route::view('/stock-movements', 'stock-movements.index')->middleware('can:stockMovements.view')->name('stock-movements.index');
    Route::view('/brands', 'brands.index')->middleware('can:brands.view')->name('brands.index');
    Route::view('/categories', 'categories.index')->middleware('can:categories.view')->name('categories.index');

    Route::view('/customers', 'customers.index')->middleware('can:customers.view')->name('customers.index');
    Route::view('/suppliers', 'suppliers.index')->middleware('can:suppliers.view')->name('suppliers.index');

    Route::view('/finance', 'finance.index')->middleware('can:finance.view')->name('finance.index');
    Route::view('/salary', 'salary.index')->middleware('can:salary.view')->name('salary.index');

    Route::view('/audit-log', 'audit-log.index')->middleware('can:auditLog.view')->name('audit-log.index');
    Route::view('/settings', 'settings.index')->name('settings.index');
    Route::view('/profile', 'profile.edit')->name('profile.edit');
});

if (app()->environment('local')) {
    Route::view('/style-test', 'style-test')->name('style-test');
}
