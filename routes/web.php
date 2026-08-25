<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseTransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Language & Guest Routes
|--------------------------------------------------------------------------
*/

Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->name('lang.switch');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Application Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile Settings
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    // Distributors
    Route::get('/distributors', [DistributorController::class, 'index'])->name('distributors.index');
    Route::get('/distributors/create', [DistributorController::class, 'create'])->name('distributors.create');
    Route::post('/distributors', [DistributorController::class, 'store'])->name('distributors.store');
    Route::get('/distributors/{distributor}', [DistributorController::class, 'show'])->name('distributors.show');
    Route::get('/distributors/{distributor}/edit', [DistributorController::class, 'edit'])->name('distributors.edit');
    Route::put('/distributors/{distributor}', [DistributorController::class, 'update'])->name('distributors.update');
    Route::delete('/distributors/{distributor}', [DistributorController::class, 'destroy'])->name('distributors.destroy');

    // Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('/warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');

    // Stock In
    Route::get('/stock-in', [StockInController::class, 'index'])->name('stock-in.index');
    Route::get('/stock-in/create', [StockInController::class, 'create'])->name('stock-in.create');
    Route::post('/stock-in', [StockInController::class, 'store'])->name('stock-in.store');
    Route::get('/stock-in/{stockIn}', [StockInController::class, 'show'])->name('stock-in.show');

    // Stock Out
    Route::get('/stock-out', [StockOutController::class, 'index'])->name('stock-out.index');
    Route::get('/stock-out/create', [StockOutController::class, 'create'])->name('stock-out.create');
    Route::post('/stock-out', [StockOutController::class, 'store'])->name('stock-out.store');
    Route::get('/stock-out/{stockOut}', [StockOutController::class, 'show'])->name('stock-out.show');

    // Stock Movements
    Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');

    // Warehouse Transfers
    Route::get('/transfers', [WarehouseTransferController::class, 'index'])->name('transfers.index');

    // Creating a transfer moves real stock between two warehouses, so it is
    // limited to Admin and Warehouse Manager. Enforced here on the server, not
    // merely by hiding the button. Viewing stays open to every role.
    //
    // These must stay declared BEFORE /transfers/{transfer} below, or the
    // wildcard would match the literal string "create" and 404 on binding.
    Route::middleware('role:Admin,Warehouse Manager')->group(function () {
        Route::get('/transfers/create', [WarehouseTransferController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [WarehouseTransferController::class, 'store'])->name('transfers.store');
    });

    Route::get('/transfers/{transfer}', [WarehouseTransferController::class, 'show'])->name('transfers.show');

    // Inventory Adjustments
    Route::get('/adjustments', [InventoryAdjustmentController::class, 'index'])->name('adjustments.index');

    // An adjustment can create or destroy stock with no counterparty document,
    // which makes it the most sensitive write in the system — restricted to
    // Admin and Warehouse Manager, enforced here rather than by hiding the
    // button. Must stay declared before /adjustments/{adjustment} below.
    Route::middleware('role:Admin,Warehouse Manager')->group(function () {
        Route::get('/adjustments/create', [InventoryAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [InventoryAdjustmentController::class, 'store'])->name('adjustments.store');
    });

    Route::get('/adjustments/{adjustment}', [InventoryAdjustmentController::class, 'show'])->name('adjustments.show');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/stock', [ReportController::class, 'stock'])->name('reports.stock');
    Route::get('/reports/movements', [ReportController::class, 'movements'])->name('reports.movements');
    Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
    Route::get('/reports/stock-in', [ReportController::class, 'stockIn'])->name('reports.stock-in');
    Route::get('/reports/stock-out', [ReportController::class, 'stockOut'])->name('reports.stock-out');

    // Report exports. {format} is constrained to pdf|csv so the controller
    // never has to defend against an arbitrary value, and an unknown format
    // 404s rather than silently falling back to one of them.
    Route::get('/reports/stock/export/{format}', [ReportController::class, 'exportStock'])
        ->name('reports.stock.export')->whereIn('format', ['pdf', 'csv']);
    Route::get('/reports/movements/export/{format}', [ReportController::class, 'exportMovements'])
        ->name('reports.movements.export')->whereIn('format', ['pdf', 'csv']);
    Route::get('/reports/low-stock/export/{format}', [ReportController::class, 'exportLowStock'])
        ->name('reports.low-stock.export')->whereIn('format', ['pdf', 'csv']);
    Route::get('/reports/stock-in/export/{format}', [ReportController::class, 'exportStockIn'])
        ->name('reports.stock-in.export')->whereIn('format', ['pdf', 'csv']);
    Route::get('/reports/stock-out/export/{format}', [ReportController::class, 'exportStockOut'])
        ->name('reports.stock-out.export')->whereIn('format', ['pdf', 'csv']);

    /*
    |--------------------------------------------------------------------------
    | Admin-Only Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:Admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Activity log — read-only. There is intentionally no create, edit or
        // delete: an audit trail an administrator can rewrite is not one.
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

});
