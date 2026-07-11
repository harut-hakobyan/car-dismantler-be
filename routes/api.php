<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CarMakeController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PartController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;


Route::get('/test', function () {
    return [
        'status' => 'ok',
        'app' => 'Car Dismantler API'
    ];
});


// Public routes
Route::post('/login', [AuthController::class, 'login']);

Route::get('/permissions', [PermissionController::class, 'index']);
Route::get('/car-makes', [CarMakeController::class, 'index']);
Route::get('/car-makes/{carMake}/models', [CarMakeController::class, 'models']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/details', [DashboardController::class, 'details']);
    Route::get('/dashboard/activity', [DashboardController::class, 'activity']);
    Route::get('/orders/recent', [DashboardController::class, 'recentOrders']);

    Route::apiResource('users', UserController::class)->except(['show']);
    Route::apiResource('roles', RoleController::class)->only(['index', 'update']);
    Route::apiResource('cars', CarController::class)->except(['show']);
    Route::get('/cars/options', [CarController::class, 'options']);
    Route::apiResource('customers', CustomerController::class)->except(['show']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'update']);
    Route::apiResource('parts', PartController::class)->except(['show']);
    Route::post('/parts/{part}/sell', [PartController::class, 'sell']);
    Route::get('/parts/options', [PartController::class, 'options']);
});
