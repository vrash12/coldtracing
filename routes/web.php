<?php

use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Driver\DashboardController as DriverDashboardController;
use App\Http\Controllers\Driver\NotificationController as DriverNotificationController;
use App\Http\Controllers\Driver\OrderController as DriverOrderController;
use App\Http\Controllers\Driver\TripController as DriverTripController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.submit');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Administrator Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Administrator')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('users', UserController::class);

        Route::resource('orders', OrderController::class);

        Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->name('orders.cancel');

        Route::get('/monitoring', [MapController::class, 'index'])
            ->name('monitoring.index');

        Route::get('/monitoring/latest', [MapController::class, 'latest'])
            ->name('monitoring.latest');
    });

    /*
    |--------------------------------------------------------------------------
    | Driver Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('driver')
        ->name('driver.')
        ->middleware('role:Driver')
        ->group(function () {
            Route::get('/dashboard', [DriverDashboardController::class, 'index'])
                ->name('dashboard');

            Route::get('/orders', [DriverOrderController::class, 'index'])
                ->name('orders.index');

            Route::post('/telemetry/software-feed', [DriverOrderController::class, 'simulateTelemetry'])
                ->name('telemetry.software-feed');

            Route::post(
                '/orders/{order}/ai-route-recommendation',
                [DriverOrderController::class, 'aiRouteRecommendation']
            )->name('orders.aiRouteRecommendation');

            Route::get('/orders/{order}', [DriverOrderController::class, 'show'])
                ->name('orders.show');

            Route::get(
                '/orders/{order}/telemetry/latest',
                [DriverOrderController::class, 'latestTelemetry']
            )->name('orders.telemetry.latest');

            /*
        |--------------------------------------------------------------------------
        | Driver Trip Routes
        |--------------------------------------------------------------------------
        */

            Route::get('/trips', [DriverTripController::class, 'index'])
                ->name('trips.index');

            Route::get('/trips/{trip}', [DriverTripController::class, 'show'])
                ->name('trips.show');

            Route::patch('/trips/{trip}/start', [DriverTripController::class, 'start'])
                ->name('trips.start');

            Route::patch('/trips/{trip}/complete', [DriverTripController::class, 'complete'])
                ->name('trips.complete');

            /*
        |--------------------------------------------------------------------------
        | Driver Notification Routes
        |--------------------------------------------------------------------------
        */

            Route::patch(
                '/notifications/{notification}/read',
                [DriverNotificationController::class, 'markAsRead']
            )->name('notifications.read');

            Route::patch(
                '/notifications/read-all',
                [DriverNotificationController::class, 'markAllAsRead']
            )->name('notifications.readAll');
        });

});
