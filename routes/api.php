<?php

use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/telemetry',
    [TelemetryController::class, 'store']
)->name('api.telemetry.store');