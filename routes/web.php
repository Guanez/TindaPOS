<?php

declare(strict_types=1);

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ModifierGroupController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Platform\StoreController as PlatformStoreController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ─── Guest: redirect to login ────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

// ─── Public: customer QR ordering ────────────────────
// The only routes in the application without `auth`. The store is resolved
// from the URL by middleware, which also 404s a store that has not switched
// online ordering on — so the whole feature ships dark until a cafe opts in.
Route::prefix('s/{storeSlug}')->middleware('public.store')->group(function () {
    Route::get('/', [PublicOrderController::class, 'menu'])->name('public.menu');

    Route::post('/orders', [PublicOrderController::class, 'place'])
        ->middleware('throttle:10,1')
        ->name('public.orders.place');
});

// Addressed by an unguessable token rather than an id, so one customer
// cannot walk another's order.
Route::get('/o/{token}', [PublicOrderController::class, 'status'])
    ->middleware('throttle:120,1')
    ->name('public.status');

// Cancelling is the customer's, but only until they pay — after that someone
// is making it. The guard lives in OrderService, not here.
Route::post('/o/{token}/cancel', [PublicOrderController::class, 'cancel'])
    ->middleware('throttle:20,1')
    ->name('public.orders.cancel');

// ─── Platform: the landlord's own screens ────────────
// Above tenancy rather than inside it. `platform` insists on a super admin
// with no store of their own, which is what makes the unscoped queries in
// this controller safe; `store.selected` is deliberately absent, because
// these are the screens you use before you have picked a store.
Route::middleware(['auth', 'active', 'platform'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', fn () => redirect()->route('platform.stores.index'));

    Route::get('/stores', [PlatformStoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/create', [PlatformStoreController::class, 'create'])->name('stores.create');
    Route::post('/stores', [PlatformStoreController::class, 'store'])->name('stores.store');
    Route::get('/stores/{store}/edit', [PlatformStoreController::class, 'edit'])->name('stores.edit');
    Route::put('/stores/{store}', [PlatformStoreController::class, 'update'])->name('stores.update');

    Route::post('/stores/{store}/suspension', [PlatformStoreController::class, 'toggleSuspension'])
        ->name('stores.suspension');

    // Stepping into a client shop, and back out again.
    Route::post('/stores/{store}/enter', [PlatformStoreController::class, 'enter'])->name('stores.enter');
    Route::post('/leave', [PlatformStoreController::class, 'leave'])->name('leave');
});

// ─── Authenticated Routes ────────────────────────────
// `store.active` signs out the staff of a suspended client; `store.selected`
// bounces a platform admin who has not stepped into a shop yet, so these
// screens always have exactly one store behind them.
Route::middleware(['auth', 'active', 'store.active', 'store.selected'])->group(function () {

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

    // Export (managers only). The screen is open to everyone because a
    // cashier needs to look a receipt up; pulling the whole history out as a
    // file is a different act, and it belongs with the other manager tools.
    // Declared before /sales/{sale} so "export" is not read as an id.
    Route::get('/sales/export', [SalesController::class, 'export'])
        ->middleware(['role:owner,admin', 'throttle:6,1'])
        ->name('sales.export');

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

    // Staff accounts (managers only)
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Store settings and the customer QR code (managers only)
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/store/settings', [StoreController::class, 'edit'])->name('store.edit');
        Route::put('/store/settings', [StoreController::class, 'update'])->name('store.update');
        Route::get('/store/qr', [StoreController::class, 'qr'])->name('store.qr');

        // Retiring the ordering address kills every printed code, so it is its
        // own route rather than a field on the settings form.
        Route::put('/store/address', [StoreController::class, 'updateAddress'])->name('store.address');
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
