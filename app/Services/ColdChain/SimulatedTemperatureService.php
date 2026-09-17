<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Trip;

final class SimulatedTemperatureService
{
    /**
     * Produce a smooth demo temperature near the product's target range.
     * This is synthetic data and must never be described as a sensor reading.
     */
    public function nextForTrip(Trip $trip): float
    {
        $trip->loadMissing(['product', 'latestTelemetry']);

        $minimum = is_numeric($trip->product?->min_temp)
            ? (float) $trip->product->min_temp
            : 2.0;
        $maximum = is_numeric($trip->product?->max_temp)
            ? (float) $trip->product->max_temp
            : 8.0;

        if ($maximum <= $minimum) {
            [$minimum, $maximum] = [2.0, 8.0];
        }

        $target = ($minimum + $maximum) / 2;
        $previous = is_numeric($trip->latestTelemetry?->temperature)
            ? (float) $trip->latestTelemetry->temperature
            : $target;

        $randomMovement = random_int(-18, 18) / 100;
        $returnToTarget = ($target - $previous) * 0.12;
        $next = $previous + $randomMovement + $returnToTarget;

        $safeMargin = min(0.25, ($maximum - $minimum) * 0.1);

        return round(max(
            $minimum + $safeMargin,
            min($maximum - $safeMargin, $next)
        ), 2);
    }
}
