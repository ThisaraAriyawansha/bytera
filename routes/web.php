<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DataToolController;
use App\Http\Controllers\GrnController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
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
    Route::middleware('can:sales.view')->group(function () {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::post('/sales/customers', [SaleController::class, 'storeCustomer'])->name('sales.customers.store');

        Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');

        Route::get('/api/products/{product}/batches', [ProductStockController::class, 'availableBatches'])->name('api.products.batches');
    });

    Route::post('/sales/{sale}/email', [SaleController::class, 'email'])->middleware('throttle:10,1')->name('sales.email');
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

    Route::middleware('can:products.view')->prefix('products')->name('products.')->scopeBindings()->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');

        Route::get('/{product}/batches', [ProductStockController::class, 'batches'])->name('batches.index');
        Route::post('/{product}/batches', [ProductStockController::class, 'storeBatch'])->name('batches.store');
        Route::put('/{product}/batches/{batch}', [ProductStockController::class, 'updateBatch'])->name('batches.update');

        Route::get('/{product}/batches/{batch}/units', [ProductStockController::class, 'units'])->name('units.index');
        Route::post('/{product}/batches/{batch}/units', [ProductStockController::class, 'storeUnits'])->name('units.store');
        Route::put('/{product}/units/{unit}', [ProductStockController::class, 'updateUnit'])->name('units.update');
        Route::delete('/{product}/units/{unit}', [ProductStockController::class, 'destroyUnit'])->name('units.destroy');
    });

    Route::middleware('can:grn.view')->prefix('grn')->name('grn.')->group(function () {
        Route::get('/', [GrnController::class, 'index'])->name('index');
        Route::get('/new', [GrnController::class, 'create'])->middleware('can:grn.create')->name('create');
        Route::post('/', [GrnController::class, 'store'])->name('store');
        Route::get('/{grn}', [GrnController::class, 'show'])->name('show');
        Route::put('/{grn}', [GrnController::class, 'update'])->name('update');
    });

    Route::middleware('can:stockTransfer.view')->prefix('stock-transfer')->name('stock-transfer.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('/new', [StockTransferController::class, 'create'])->middleware('can:stockTransfer.create')->name('create');
        Route::post('/', [StockTransferController::class, 'store'])->name('store');
        Route::get('/{stockTransfer}', [StockTransferController::class, 'show'])->name('show');
        Route::put('/{stockTransfer}', [StockTransferController::class, 'update'])->name('update');
    });

    Route::middleware('can:stockOut.view')->prefix('stock-out')->name('stock-out.')->group(function () {
        Route::get('/', [StockOutController::class, 'index'])->name('index');
        Route::get('/new', [StockOutController::class, 'create'])->middleware('can:stockOut.create')->name('create');
        Route::post('/', [StockOutController::class, 'store'])->name('store');
        Route::get('/{stockOut}', [StockOutController::class, 'show'])->name('show');
        Route::put('/{stockOut}', [StockOutController::class, 'update'])->name('update');
    });

    Route::middleware('can:stockMovements.view')->prefix('stock-movements')->name('stock-movements.')->group(function () {
        Route::get('/', [StockMovementController::class, 'index'])->name('index');
        Route::get('/export', [StockMovementController::class, 'export'])->name('export');
    });

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

    Route::middleware('can:suppliers.view')->prefix('suppliers')->name('suppliers.')->scopeBindings()->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::post('/', [SupplierController::class, 'store'])->name('store');

        Route::get('/payments', [SupplierPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/export', [SupplierPaymentController::class, 'export'])->name('payments.export');

        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::post('/{supplier}/statement', [SupplierController::class, 'sendStatement'])->name('statement');
        Route::post('/{supplier}/payments', [SupplierPaymentController::class, 'store'])->name('payments.store');
        Route::put('/{supplier}/payments/{payment}', [SupplierPaymentController::class, 'update'])->name('payments.update');
    });

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
    Route::get('/api/jobs/search', [JobController::class, 'search'])->name('api.jobs.search');
    Route::get('/api/products/{product}/units', [ProductStockController::class, 'availableUnits'])->name('api.products.units');

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
