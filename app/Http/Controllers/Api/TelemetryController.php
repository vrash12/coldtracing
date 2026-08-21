<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ColdChain\TelemetryProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TelemetryController extends Controller
{
    public function store(
        Request $request,
        TelemetryProcessingService $telemetryProcessor
    ): JsonResponse {
        $validated = $request->validate([
            'device_code' => [
                'required',
                'string',
                'max:255',
                Rule::in(array_keys(config('coldtrace.devices', []))),
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'temperature' => [
                'required',
                'numeric',
                'between:-100,100',
            ],

            'humidity' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'recorded_at' => [
                'nullable',
                'date',
            ],
        ]);

        try {
            $telemetryLog = $telemetryProcessor->process(
                $validated
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Telemetry recorded successfully.',

            'data' => [
                'id' => $telemetryLog->id,
                'trip_id' => $telemetryLog->trip_id,
                'device_id' => $telemetryLog->device_id,
                'temperature' => $telemetryLog->temperature,
                'humidity' => $telemetryLog->humidity,
                'latitude' => $telemetryLog->latitude,
                'longitude' => $telemetryLog->longitude,
                'mkt_value' => $telemetryLog->mkt_value,
                'rsl_hours' => $telemetryLog->rsl_hours,
                'recorded_at' => $telemetryLog->recorded_at,
            ],
        ], 201);
    }
}
