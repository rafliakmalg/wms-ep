<?php

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::apiResource('adjustments', AdjustmentController::class)->only(['index', 'show', 'store']);
    Route::post('adjustments/{adjustment}/cancel', [AdjustmentController::class, 'cancel'])->name('adjustments.cancel');
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard.show');
    Route::apiResource('handovers', HandoverController::class)->only(['index', 'show', 'store']);
    Route::post('handovers/{handover}/cancel', [HandoverController::class, 'cancel'])->name('handovers.cancel');
    Route::post('handovers/{handover}/complete', [HandoverController::class, 'complete'])->name('handovers.complete');
    Route::post('handovers/{handover}/transfer', [HandoverController::class, 'transfer'])->name('handovers.transfer');
    Route::apiResource('inventories', InventoryController::class)->only('index');
    Route::apiResource('movements', MovementController::class)->only(['index', 'store']);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('racks', RackController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('users', UserController::class)->only('index');
    Route::apiResource('warehouses', WarehouseController::class);

    Route::middleware('can:manage-users')->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('users', UserController::class)->except('index');
    });
});
