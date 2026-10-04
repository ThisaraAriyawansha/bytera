<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DataToolController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamMemberController;
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

    Route::middleware('can:services.view')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    Route::view('/bills', 'bills.index')->middleware('can:bills.view')->name('bills.index');
    Route::view('/quotations', 'quotations.index')->middleware('can:quotations.view')->name('quotations.index');
    Route::view('/warranty', 'warranty.index')->middleware('can:warranty.view')->name('warranty.index');

    Route::view('/products', 'products.index')->middleware('can:products.view')->name('products.index');
    Route::view('/grn', 'grn.index')->middleware('can:grn.view')->name('grn.index');
    Route::view('/stock-transfer', 'stock-transfer.index')->middleware('can:stockTransfer.view')->name('stock-transfer.index');
    Route::view('/stock-out', 'stock-out.index')->middleware('can:stockOut.view')->name('stock-out.index');
    Route::view('/stock-movements', 'stock-movements.index')->middleware('can:stockMovements.view')->name('stock-movements.index');

    Route::middleware('can:brands.view')->group(function () {
        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
        Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });

    Route::middleware('can:categories.view')->prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/main', [CategoryController::class, 'storeMain'])->name('main.store');
        Route::put('/main/{mainCategory}', [CategoryController::class, 'updateMain'])->name('main.update');
        Route::delete('/main/{mainCategory}', [CategoryController::class, 'destroyMain'])->name('main.destroy');
        Route::post('/sub', [CategoryController::class, 'storeSub'])->name('sub.store');
        Route::put('/sub/{subCategory}', [CategoryController::class, 'updateSub'])->name('sub.update');
        Route::delete('/sub/{subCategory}', [CategoryController::class, 'destroySub'])->name('sub.destroy');
    });

    Route::middleware('can:customers.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });
    Route::view('/suppliers', 'suppliers.index')->middleware('can:suppliers.view')->name('suppliers.index');

    Route::view('/finance', 'finance.index')->middleware('can:finance.view')->name('finance.index');
    Route::view('/salary', 'salary.index')->middleware('can:salary.view')->name('salary.index');

    Route::view('/audit-log', 'audit-log.index')->middleware('can:auditLog.view')->name('audit-log.index');
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/shop', [SettingsController::class, 'updateShop'])->name('shop.update');
        Route::post('/notify-emails', [SettingsController::class, 'storeNotifyEmail'])->name('notify-emails.store');
        Route::delete('/notify-emails', [SettingsController::class, 'destroyNotifyEmail'])->name('notify-emails.destroy');

        Route::post('/users', [TeamMemberController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [TeamMemberController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [TeamMemberController::class, 'destroy'])->name('users.destroy');

        Route::get('/data/{table}/export/{format}', [DataToolController::class, 'export'])
            ->whereIn('format', ['csv', 'json'])
            ->name('data.export');
        Route::delete('/data/{table}', [DataToolController::class, 'clean'])->name('data.clean');
    });

    Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::post('/email', [ProfileController::class, 'requestEmailChange'])->middleware('throttle:6,1')->name('email.request');
        Route::get('/email/confirm/{user}', [ProfileController::class, 'confirmEmailChange'])->middleware('signed')->name('email.confirm');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    });
});

if (app()->environment('local')) {
    Route::view('/style-test', 'style-test')->name('style-test');
}
