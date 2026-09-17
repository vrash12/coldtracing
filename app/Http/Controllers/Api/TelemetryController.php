<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ColdChain\TelemetryIngestionService;
use App\Services\ColdChain\TemperatureStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TelemetryController extends Controller
{
    public function store(
        Request $request,
        TelemetryIngestionService $ingestion,
        TemperatureStatusService $temperatureStatus
    ): JsonResponse {
        $this->authorizeTelemetryDevice($request);

        try {
            $telemetryLog = $ingestion->ingest($request->all());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $temperatureState = $temperatureStatus->evaluate(
            $telemetryLog->temperature,
            $telemetryLog->trip?->product
        );

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
                'temperature_status' => $temperatureState['label'],
                'temperature_status_code' => $temperatureState['code'],
                'recorded_at' => $telemetryLog->recorded_at,
            ],
        ], 201);
    }

    private function authorizeTelemetryDevice(Request $request): void
    {
        $expectedToken = (string) config(
            'coldtrace.telemetry.token',
            ''
        );

        /*
         * Existing local installations remain compatible when no token has
         * been configured. Production deployments should always set one.
         */
        if ($expectedToken === '') {
            return;
        }

        $providedToken = (string) $request->header(
            'X-ColdTrace-Token',
            ''
        );

        if (
            $providedToken === ''
            || ! hash_equals($expectedToken, $providedToken)
        ) {
            abort(401, 'Invalid ColdTrace telemetry device token.');
        }
    }
}
