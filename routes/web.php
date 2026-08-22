<?php

declare(strict_types=1);

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ModifierGroupController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

// ─── Guest: redirect to login ────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

// ─── Authenticated Routes ────────────────────────────
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // POS Terminal (all roles)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])
        ->middleware('throttle:30,1')
        ->name('pos.checkout');

    // Order queue (all roles — the cashier and the barista both work it)
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}/settle', [OrderController::class, 'settle'])->name('orders.settle');
    Route::post('/orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
    Route::post('/orders/{order}/ready', [OrderController::class, 'ready'])->name('orders.ready');
    Route::post('/orders/{order}/collect', [OrderController::class, 'collect'])->name('orders.collect');

    // Sales History (all roles)
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SalesController::class, 'show'])->name('sales.show');

    // Sales void (managers only)
    Route::post('/sales/{sale}/void', [SalesController::class, 'voidSale'])
        ->middleware(['role:owner,admin', 'throttle:10,1'])
        ->name('sales.void');

    // Inventory (managers only)
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::put('/inventory/{product}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::delete('/inventory/{product}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        Route::post('/inventory/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
        Route::get('/inventory/logs', [InventoryController::class, 'logs'])->name('inventory.logs');
    });

    // Categories (managers only)
    Route::middleware('role:owner,admin')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Add-on groups (managers only)
    Route::middleware('role:owner,admin')->group(function () {
        Route::post('/modifier-groups', [ModifierGroupController::class, 'store'])->name('modifierGroups.store');
        Route::put('/modifier-groups/{modifier_group}', [ModifierGroupController::class, 'update'])->name('modifierGroups.update');
        Route::delete('/modifier-groups/{modifier_group}', [ModifierGroupController::class, 'destroy'])->name('modifierGroups.destroy');
    });

    // Reports (managers only)
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
        Route::get('/reports/range', [ReportController::class, 'range'])->name('reports.range');
        Route::get('/reports/top-products', [ReportController::class, 'topProducts'])->name('reports.topProducts');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
