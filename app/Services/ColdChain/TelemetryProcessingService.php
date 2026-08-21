<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Device;
use App\Models\TelemetryLog;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TelemetryProcessingService
{
    public function __construct(
        private readonly MktCalculatorService $mktCalculator,
        private readonly RemainingShelfLifeService $rslCalculator
    ) {}

    /**
     * Save and process a new telemetry reading.
     *
     * @param array{
     *     device_code: string,
     *     latitude?: float|int|string|null,
     *     longitude?: float|int|string|null,
     *     temperature?: float|int|string|null,
     *     humidity?: float|int|string|null,
     *     recorded_at?: string|null
     * } $payload
     */
    public function process(array $payload): TelemetryLog
    {
        return DB::transaction(function () use ($payload) {
            $device = Device::query()
                ->where('device_code', $payload['device_code'])
                ->first();

            if (! $device) {
                throw new InvalidArgumentException(
                    'No device was found for code: '
                    .$payload['device_code']
                );
            }

            if ($device->truck_id === null) {
                throw new InvalidArgumentException(
                    'Device '
                    .$device->device_code
                    .' is registered but is not assigned to a truck.'
                );
            }

            $trip = $this->findActiveTrip($device);

            if (! $trip) {
                throw new InvalidArgumentException(
                    'No active trip is connected to this device.'
                );
            }

            $recordedAt = ! empty($payload['recorded_at'])
                ? Carbon::parse($payload['recorded_at'])
                : now();

            /*
             * First save the raw telemetry reading.
             */
            $telemetryLog = TelemetryLog::create([
                'trip_id' => $trip->id,
                'device_id' => $device->id,
                'latitude' => $this->nullableFloat(
                    $payload['latitude'] ?? null
                ),
                'longitude' => $this->nullableFloat(
                    $payload['longitude'] ?? null
                ),
                'temperature' => $this->nullableFloat(
                    $payload['temperature'] ?? null
                ),
                'humidity' => $this->nullableFloat(
                    $payload['humidity'] ?? null
                ),
                'mkt_value' => null,
                'rsl_hours' => null,
                'recorded_at' => $recordedAt,
            ]);

            /*
             * Update the device heartbeat.
             */
            $device->update([
                'last_seen_at' => $recordedAt,
                'status' => 'active',
            ]);

            /*
             * Calculate MKT and RSL using all valid temperature
             * readings recorded during this trip.
             */
            $this->calculateColdChainValues(
                trip: $trip,
                latestTelemetryLog: $telemetryLog
            );

            return $telemetryLog->fresh([
                'trip.product',
                'device',
            ]);
        });
    }

    private function calculateColdChainValues(
        Trip $trip,
        TelemetryLog $latestTelemetryLog
    ): void {
        if ($latestTelemetryLog->temperature === null) {
            return;
        }

        $temperatureLogs = TelemetryLog::query()
            ->where('trip_id', $trip->id)
            ->whereNotNull('temperature')
            ->orderBy('recorded_at')
            ->get([
                'temperature',
                'recorded_at',
            ]);

        $mkt = $this->mktCalculator->calculateFromTelemetry(
            $temperatureLogs
        );

        if ($mkt === null) {
            return;
        }

        $trip->loadMissing('product');

        if (! $trip->product) {
            $latestTelemetryLog->update([
                'mkt_value' => $mkt,
            ]);

            return;
        }

        $elapsedHours = $this->calculateElapsedHours(
            $trip,
            $latestTelemetryLog
        );

        $rsl = $this->rslCalculator->calculateForProduct(
            product: $trip->product,
            elapsedHours: $elapsedHours,
            mktCelsius: $mkt
        );

        $latestTelemetryLog->update([
            'mkt_value' => $mkt,
            'rsl_hours' => $rsl['remaining_hours'],
        ]);
    }

    private function findActiveTrip(Device $device): ?Trip
    {
        return Trip::query()
            ->with('product')
            ->where('truck_id', $device->truck_id)
            ->whereIn('status', [
                'pending',
                'in_progress',
            ])
            ->latest('started_at')
            ->latest('id')
            ->first();
    }

    private function calculateElapsedHours(
        Trip $trip,
        TelemetryLog $telemetryLog
    ): float {
        $startTime = $trip->started_at
            ?? $trip->created_at
            ?? $telemetryLog->recorded_at;

        if (! $startTime || ! $telemetryLog->recorded_at) {
            return 0.0;
        }

        return max(
            0,
            $startTime->diffInSeconds(
                $telemetryLog->recorded_at
            ) / 3600
        );
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                'Telemetry values must be numeric.'
            );
        }

        return (float) $value;
    }
}
