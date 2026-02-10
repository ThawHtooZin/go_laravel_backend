<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverStatusController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RideController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Public auth endpoints
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register-passenger', [AuthController::class, 'registerPassenger']);
Route::post('/auth/register-driver', [AuthController::class, 'registerDriver']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Current user rides
    Route::get('/my/rides', [RideController::class, 'myRides']);

    // Passenger ride creation
    Route::post('/rides', [RideController::class, 'store']);

    // Driver: available rides, accept, arrived at pickup, start trip, complete
    Route::get('/rides/available', [RideController::class, 'availableRides'])->middleware('role:driver');
    Route::post('/rides/{ride}/accept', [RideController::class, 'accept'])->middleware('role:driver');
    Route::post('/rides/{ride}/arrived-pickup', [RideController::class, 'arrivedPickup'])->middleware('role:driver');
    Route::post('/rides/{ride}/start', [RideController::class, 'startTrip'])->middleware('role:driver');
    Route::post('/rides/{ride}/complete', [RideController::class, 'complete'])->middleware('role:driver');

    // Driver online status and location (driver only)
    Route::post('/driver/online', [DriverStatusController::class, 'online']);
    Route::post('/driver/offline', [DriverStatusController::class, 'offline']);
    Route::post('/driver/location', [DriverStatusController::class, 'location']);

    // Admin dashboard: admin or super_admin
    Route::middleware('role:admin|super_admin')->prefix('admin')->group(function () {
        Route::get('/stats', [UserController::class, 'stats']);

        // Users (no role change to admin/super_admin unless super_admin)
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}', [UserController::class, 'update']);

        // Drivers onboarding and creation
        Route::get('/drivers', [UserController::class, 'drivers']);
        Route::post('/drivers', [UserController::class, 'storeDriver']);
        Route::post('/drivers/{user}/approve', [UserController::class, 'approveDriver']);
        Route::post('/drivers/{user}/reject', [UserController::class, 'rejectDriver']);

        // Users creation
        Route::post('/users', [UserController::class, 'storeUser']);

        // Rides
        Route::get('/rides', [RideController::class, 'index']);
        Route::post('/rides/{ride}/assign', [RideController::class, 'assignDriver']);
        Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel']);

        // Driver status logs
        Route::get('/driver-status-logs', [UserController::class, 'driverStatusLogs']);

        // God's Eye: online drivers with coordinates
        Route::get('/drivers/locations', [UserController::class, 'driverLocations']);

        // Super-admin only: manage admins
        Route::get('/admins', [UserController::class, 'indexAdmins'])->middleware('role:super_admin');
        Route::post('/admins', [UserController::class, 'storeAdmin'])->middleware('role:super_admin');
        Route::patch('/admins/{user}', [UserController::class, 'updateAdmin'])->middleware('role:super_admin');
    });
});

