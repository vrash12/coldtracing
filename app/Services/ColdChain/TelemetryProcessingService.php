<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Device;
use App\Models\TelemetryLog;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class TelemetryProcessingService
{
    public function __construct(
        private readonly MktCalculatorService $mktCalculator,
        private readonly RemainingShelfLifeService $rslCalculator,
        private readonly TemperatureAlertService $temperatureAlerts
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

            /*
             * Alert state follows the chronologically latest trip reading.
             * A delayed historical upload must not overwrite the current
             * temperature condition.
             */
            $currentTelemetryLog = TelemetryLog::query()
                ->where('trip_id', $trip->id)
                ->latest('recorded_at')
                ->latest('id')
                ->firstOrFail();

            $this->temperatureAlerts->synchronize(
                trip: $trip,
                telemetryLog: $currentTelemetryLog
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

        $trip->loadMissing('product');

        $temperatureLogs = TelemetryLog::query()
            ->where('trip_id', $trip->id)
            ->whereNotNull('temperature')
            ->orderBy('recorded_at')
            ->get([
                'temperature',
                'recorded_at',
            ]);

        $productActivationEnergy = $this->productActivationEnergy(
            $trip
        );

        $mkt = $this->mktCalculator->calculateFromTelemetry(
            telemetryLogs: $temperatureLogs,
            activationEnergyJPerMol: $productActivationEnergy
        );

        if ($mkt === null) {
            return;
        }

        if (! $trip->product) {
            $latestTelemetryLog->update([
                'mkt_value' => $mkt,
            ]);

            return;
        }

        $latestTelemetryLog->update([
            'mkt_value' => $mkt,
            'rsl_hours' => null,
        ]);

        $profileIssues = $this->rslCalculator
            ->productProfileIssues($trip->product);

        if ($profileIssues !== []) {
            Log::warning(
                'ColdTrace skipped the remaining shelf-life estimate because the product scientific profile is incomplete.',
                [
                    'trip_id' => $trip->id,
                    'product_id' => $trip->product->id,
                    'issues' => $profileIssues,
                ]
            );

            return;
        }

        $elapsedHours = $this->calculateElapsedHours(
            $trip,
            $latestTelemetryLog
        );

        try {
            $rsl = $this->rslCalculator->calculateForProduct(
                product: $trip->product,
                elapsedHours: $elapsedHours,
                mktCelsius: $mkt
            );
        } catch (InvalidArgumentException $exception) {
            Log::warning(
                'ColdTrace could not calculate the remaining shelf-life estimate.',
                [
                    'trip_id' => $trip->id,
                    'product_id' => $trip->product->id,
                    'message' => $exception->getMessage(),
                ]
            );

            return;
        }

        $latestTelemetryLog->update([
            'mkt_value' => $mkt,
            'rsl_hours' => $rsl['remaining_hours'],
        ]);
    }

    private function productActivationEnergy(Trip $trip): ?float
    {
        $activationEnergy = $trip->product
            ? data_get(
                $trip->product,
                'activation_energy_j_per_mol'
            )
            : null;

        if (
            ! is_numeric($activationEnergy)
            || ! is_finite((float) $activationEnergy)
            || (float) $activationEnergy <= 0
        ) {
            return null;
        }

        return (float) $activationEnergy;
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
