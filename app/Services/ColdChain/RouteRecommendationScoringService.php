<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

final class RouteRecommendationScoringService
{
    private const WEIGHT_ETA = 0.35;

    private const WEIGHT_DISTANCE = 0.20;

    private const WEIGHT_TEMPERATURE = 0.25;

    private const WEIGHT_RSL = 0.20;

    /**
     * Rebuild the recommendation payload using database-owned cargo data.
     * Only route ETA and distance are accepted from the browser because they
     * originate in Google Routes; all risk values and scores are discarded.
     *
     * @param  array<int, array<string, mixed>>  $routeOptions
     * @return array<string, mixed>
     */
    public function score(Order $order, int $driverId, array $routeOptions): array
    {
        $order->loadMissing([
            'receiver',
            'orderItems.product',
            'trip.product',
            'trip.latestTelemetry.device',
        ]);

        if ((int) $order->driver_id !== $driverId) {
            throw ValidationException::withMessages([
                'order_id' => 'The order is not assigned to the authenticated driver.',
            ]);
        }

        $trip = $order->trip;

        if ($trip && (int) $trip->driver_id !== $driverId) {
            throw ValidationException::withMessages([
                'order_id' => 'The order trip is not owned by the authenticated driver.',
            ]);
        }

        $latestTelemetry = $trip?->latestTelemetry;

        if ($latestTelemetry) {
            $device = $latestTelemetry->device;

            if (! $device || (int) $device->truck_id !== (int) $trip->truck_id) {
                throw ValidationException::withMessages([
                    'device' => 'The latest telemetry device is not assigned to this order\'s truck.',
                ]);
            }
        }

        $product = $trip?->product ?? $order->orderItems->first()?->product;
        $temperature = $latestTelemetry?->temperature !== null
            ? (float) $latestTelemetry->temperature
            : null;
        $remainingShelfLife = $latestTelemetry?->rsl_hours !== null
            ? (float) $latestTelemetry->rsl_hours
            : null;

        $temperatureRisk = $this->temperatureRisk(
            $temperature,
            $product?->min_temp !== null ? (float) $product->min_temp : null,
            $product?->max_temp !== null ? (float) $product->max_temp : null,
        );

        $maxEtaMinutes = max(array_map(
            fn (array $route): float => (float) $route['eta_minutes'],
            $routeOptions
        ));
        $maxDistanceKm = max(array_map(
            fn (array $route): float => (float) $route['distance_km'],
            $routeOptions
        ));

        $scoredRoutes = array_map(function (array $route) use (
            $maxEtaMinutes,
            $maxDistanceKm,
            $temperatureRisk,
            $remainingShelfLife,
            $product
        ): array {
            $etaMinutes = (float) $route['eta_minutes'];
            $distanceKm = (float) $route['distance_km'];
            $etaNorm = $etaMinutes / $maxEtaMinutes;
            $distanceNorm = $distanceKm / $maxDistanceKm;

            // RSL is projected at arrival so different ETAs can carry
            // different cold-chain risks even when current RSL is identical.
            $projectedRslHours = $remainingShelfLife !== null
                ? $remainingShelfLife - ($etaMinutes / 60)
                : null;
            $rslRisk = $this->remainingShelfLifeRisk(
                $projectedRslHours,
                $product?->initial_shelf_life_hours !== null
                    ? (float) $product->initial_shelf_life_hours
                    : null,
            );
            $finalRisk = $rslRisk['penalty'] > $temperatureRisk['penalty']
                ? $rslRisk
                : $temperatureRisk;

            $score =
                (self::WEIGHT_ETA * $etaNorm)
                + (self::WEIGHT_DISTANCE * $distanceNorm)
                + (self::WEIGHT_TEMPERATURE * $temperatureRisk['score'])
                + (self::WEIGHT_RSL * $rslRisk['score']);

            return [
                'route_id' => $route['route_id'],
                'original_index' => $route['original_index'] ?? null,
                'eta_minutes' => round($etaMinutes, 1),
                'distance_km' => round($distanceKm, 2),
                'eta_norm' => round($etaNorm, 3),
                'distance_norm' => round($distanceNorm, 3),
                'temperature_risk' => $temperatureRisk['score'],
                'temperature_risk_label' => $temperatureRisk['label'],
                'rsl_risk' => $rslRisk['score'],
                'rsl_risk_label' => $rslRisk['label'],
                'projected_rsl_hours_at_arrival' => $projectedRslHours !== null
                    ? round($projectedRslHours, 2)
                    : null,
                'route_score' => round($score, 3),
                'risk_label' => $finalRisk['label'],
                'risk_level' => $finalRisk['level'],
            ];
        }, $routeOptions);

        usort($scoredRoutes, function (array $left, array $right): int {
            return [$left['route_score'], $left['eta_minutes'], $left['distance_km'], $left['route_id']]
                <=> [$right['route_score'], $right['eta_minutes'], $right['distance_km'], $right['route_id']];
        });

        return [
            'server_verified_order' => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'driver_id' => $order->driver_id,
                'receiver_name' => $order->receiver?->name,
                'delivery_address' => $order->delivery_address,
                'expected_delivery_at' => $order->expected_delivery_at?->toIso8601String(),
                'status' => $order->status,
            ],
            'server_verified_product' => [
                'id' => $product?->id,
                'name' => $product?->name,
                'min_temp' => $product?->min_temp !== null ? (float) $product->min_temp : null,
                'max_temp' => $product?->max_temp !== null ? (float) $product->max_temp : null,
                'initial_shelf_life_hours' => $product?->initial_shelf_life_hours !== null
                    ? (float) $product->initial_shelf_life_hours
                    : null,
            ],
            'server_verified_condition' => [
                'telemetry_log_id' => $latestTelemetry?->id,
                'device_code' => $latestTelemetry?->device?->device_code,
                'temperature' => $temperature,
                'rsl_hours' => $remainingShelfLife,
                'recorded_at' => $latestTelemetry?->recorded_at?->toIso8601String(),
            ],
            'scoring_weights' => [
                'eta' => self::WEIGHT_ETA,
                'distance' => self::WEIGHT_DISTANCE,
                'temperature' => self::WEIGHT_TEMPERATURE,
                'rsl' => self::WEIGHT_RSL,
            ],
            'route_options' => array_values($scoredRoutes),
            'lowest_score_route_id' => $scoredRoutes[0]['route_id'],
        ];
    }

    /** @return array{label: string, level: string, penalty: int, score: float} */
    private function temperatureRisk(?float $temperature, ?float $minimum, ?float $maximum): array
    {
        if ($temperature === null || $minimum === null || $maximum === null) {
            return $this->risk('Unknown', 'warning', 300, 0.50);
        }

        if ($temperature < $minimum) {
            return $this->risk('Medium', 'warning', 500, 0.50);
        }

        if ($temperature > $maximum) {
            return $this->risk('High', 'critical', 1200, 1.00);
        }

        return $this->risk('Low', 'safe', 0, 0.00);
    }

    /** @return array{label: string, level: string, penalty: int, score: float} */
    private function remainingShelfLifeRisk(?float $remainingHours, ?float $initialShelfLifeHours): array
    {
        if ($remainingHours === null) {
            return $this->risk('Unknown', 'warning', 300, 0.50);
        }

        $percentage = $initialShelfLifeHours !== null && $initialShelfLifeHours > 0
            ? max(0, min(100, ($remainingHours / $initialShelfLifeHours) * 100))
            : null;

        if ($remainingHours <= 0 || ($percentage !== null && $percentage <= 10)) {
            return $this->risk('Critical', 'critical', 3000, 1.00);
        }

        if ($percentage !== null && $percentage <= 30) {
            return $this->risk('High', 'warning', 1200, 1.00);
        }

        return $this->risk('Low', 'safe', 0, 0.00);
    }

    /** @return array{label: string, level: string, penalty: int, score: float} */
    private function risk(string $label, string $level, int $penalty, float $score): array
    {
        return compact('label', 'level', 'penalty', 'score');
    }
}
