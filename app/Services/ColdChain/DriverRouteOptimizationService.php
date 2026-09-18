<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Order;
use App\Services\OpenAIRouteRecommendationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class DriverRouteOptimizationService
{
    private const GOOGLE_ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct(private readonly OpenAIRouteRecommendationService $openAi) {}

    /**
     * Google supplies road routes; ColdTrace scores each delivery at its arrival
     * time and asks AI to choose only among those verified routes. Candidate
     * generation is bounded, not an exhaustive search of every possible tour.
     */
    public function optimize(array $origin, Collection $orders, Collection $stops, int $driverId): array
    {
        if ($orders->isEmpty() || $orders->count() > 25 || $stops->count() !== $orders->count()) {
            throw ValidationException::withMessages([
                'orders' => 'Route planning requires between 1 and 25 active deliveries with valid locations.',
            ]);
        }

        foreach ($stops as $stop) {
            if (! $this->validCoordinates($stop['lat'], $stop['lng'])) {
                throw ValidationException::withMessages(['orders' => 'An assigned delivery has an invalid map location. Ask the administrator to correct it.']);
            }
        }

        $conditions = $this->orderConditions($orders, $driverId);
        $apiKey = config('services.google_maps.routes_key') ?: config('services.google_maps.key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RouteOptimizationUnavailable('Road routing is not configured. Ask the administrator to configure the Google Routes API key.', 'ROUTES_NOT_CONFIGURED');
        }

        $candidates = [];
        $warnings = [];
        foreach ($this->destinationCandidates($origin, $stops, $orders) as $destinationIndex) {
            $intermediates = $stops->except($destinationIndex)->values();
            $request = [
                'origin' => $this->waypoint($origin),
                'destination' => $this->waypoint($stops[$destinationIndex]),
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_AWARE',
                'computeAlternativeRoutes' => $stops->count() === 1,
                'polylineEncoding' => 'ENCODED_POLYLINE',
            ];
            if ($intermediates->isNotEmpty()) {
                $request['intermediates'] = $intermediates->map(fn (array $stop): array => $this->waypoint($stop))->all();
                $request['optimizeWaypointOrder'] = true;
            }

            try {
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.legs.duration,routes.legs.distanceMeters,routes.optimizedIntermediateWaypointIndex',
                ])->connectTimeout(3)->timeout(8)->post(self::GOOGLE_ENDPOINT, $request);
            } catch (ConnectionException) {
                throw new RouteOptimizationUnavailable('The road routing service did not respond. Try again shortly.', 'ROUTES_TIMEOUT');
            }

            if ($response->failed()) {
                throw $this->googleFailure($response);
            }

            $routes = $response->json('routes');
            foreach (array_slice(is_array($routes) ? $routes : [], 0, $stops->count() === 1 ? 3 : 1) as $route) {
                if (! is_array($route)) {
                    continue;
                }
                // Google can omit requested numeric fields whose value is zero,
                // for example a leg between two deliveries at one address.
                if (! array_key_exists('distanceMeters', $route)) {
                    $route['distanceMeters'] = 0;
                }
                if (is_array($route['legs'] ?? null)) {
                    foreach ($route['legs'] as &$leg) {
                        if (is_array($leg) && ! array_key_exists('distanceMeters', $leg)) {
                            $leg['distanceMeters'] = 0;
                        }
                    }
                    unset($leg);
                }
                $orderedStops = $this->orderedStops($route, $intermediates, $stops[$destinationIndex]);
                if ($orderedStops === null || ! $this->validRoute($route, count($orderedStops))) {
                    continue;
                }
                $candidates[] = [
                    'route_id' => 'route_'.(count($candidates) + 1),
                    'route' => $route,
                    'orderedStops' => $orderedStops,
                ];
            }
        }

        if ($candidates === []) {
            throw new RouteOptimizationUnavailable('Google could not provide a complete road route for these deliveries. Check each delivery location and try again.', 'NO_VALID_ROAD_ROUTE');
        }

        $payload = $this->scoreCandidates($candidates, $conditions);
        $recommendation = $this->openAi->recommend($payload);
        $selected = collect($candidates)->firstWhere('route_id', $recommendation['recommended_route_id'] ?? null);
        if ($selected === null) {
            throw new RouteOptimizationUnavailable('No verified route was selected. Please try again.', 'INVALID_ROUTE_SELECTION');
        }
        if (collect($conditions)->contains(fn (array $condition): bool => $condition['temperature'] === null || collect($condition['products'])->contains(fn (array $product): bool => $product['rsl_hours'] === null || $product['min_temp'] === null || $product['max_temp'] === null))) {
            $warnings[] = 'Some deliveries have no fresh saved temperature readings, shelf-life estimate, or product limits. Their cargo condition is unknown; it was not assumed safe.';
        }
        if (count($candidates) === 1) {
            $warnings[] = 'Google returned one usable road route. No alternative route was available to compare.';
        }

        return [
            'orderedStops' => $selected['orderedStops'],
            'route' => $selected['route'],
            'strategy' => ($recommendation['decision_source'] ?? null) === 'openai'
                ? 'AI recommendation using verified Google road routes and all assigned deliveries'
                : 'Server-scored Google road route (AI recommendation unavailable)',
            'recommendation' => $recommendation,
            'warnings' => $warnings,
        ];
    }

    private function destinationCandidates(array $origin, Collection $stops, Collection $orders): array
    {
        // Test a late-deadline final stop and the two furthest final stops. Google
        // optimizes all other stops for each candidate; never more than 3 calls.
        $latestDeadline = $orders->sortBy(fn (Order $order): int => $order->expected_delivery_at?->timestamp ?? PHP_INT_MAX)->last();
        $deadlineIndex = $stops->search(fn (array $stop): bool => $stop['id'] === (int) $latestDeadline->id);
        $byDistance = $stops->keys()->sortByDesc(function (int $index) use ($origin, $stops): float {
            $latDelta = deg2rad($stops[$index]['lat'] - $origin['lat']);
            $lngDelta = deg2rad($stops[$index]['lng'] - $origin['lng']);

            return sin($latDelta / 2) ** 2 + cos(deg2rad($origin['lat'])) * cos(deg2rad($stops[$index]['lat'])) * sin($lngDelta / 2) ** 2;
        });

        return array_slice(array_values(array_unique([$deadlineIndex, ...$byDistance->all()])), 0, 3);
    }

    private function waypoint(array $point): array
    {
        return ['location' => ['latLng' => ['latitude' => (float) $point['lat'], 'longitude' => (float) $point['lng']]]];
    }

    private function validCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude)
            && is_finite((float) $latitude) && is_finite((float) $longitude)
            && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180
            && ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
    }

    private function orderedStops(array $route, Collection $intermediates, array $destination): ?array
    {
        $indices = $route['optimizedIntermediateWaypointIndex'] ?? [];
        if (! is_array($indices) || count($indices) !== $intermediates->count()) {
            return null;
        }
        $expected = $intermediates->isEmpty() ? [] : range(0, $intermediates->count() - 1);
        $sorted = $indices;
        sort($sorted);
        if ($sorted !== $expected) {
            return null;
        }

        return [...array_map(fn (int $index): array => $intermediates[$index], $indices), $destination];
    }

    private function validRoute(array $route, int $stopCount): bool
    {
        $polyline = data_get($route, 'polyline.encodedPolyline');
        if (! is_string($polyline) || $polyline === '' || preg_match('/[^?-~]/', $polyline)
            || $this->seconds($route['duration'] ?? null) === null
            || ! is_numeric($route['distanceMeters'] ?? null) || ! is_finite((float) $route['distanceMeters']) || (float) $route['distanceMeters'] < 0
            || ! is_array($route['legs'] ?? null) || count($route['legs']) !== $stopCount) {
            return false;
        }
        foreach ($route['legs'] as $leg) {
            if (! is_array($leg) || $this->seconds($leg['duration'] ?? null) === null
                || ! is_numeric($leg['distanceMeters'] ?? null) || ! is_finite((float) $leg['distanceMeters']) || (float) $leg['distanceMeters'] < 0) {
                return false;
            }
        }

        return true;
    }

    private function seconds(mixed $duration): ?float
    {
        return is_string($duration) && preg_match('/^\d+(?:\.\d+)?s$/D', $duration)
            && is_finite((float) $duration) ? (float) $duration : null;
    }

    private function googleFailure(Response $response): RouteOptimizationUnavailable
    {
        $reasons = collect($response->json('error.details', []))->pluck('reason')->filter()->all();
        $status = $response->json('error.status');
        if (in_array('SERVICE_DISABLED', $reasons, true)) {
            return new RouteOptimizationUnavailable('Google Routes API is disabled for this project. Ask the administrator to enable Routes API in Google Cloud, then try again.', 'SERVICE_DISABLED');
        }
        if (in_array('BILLING_DISABLED', $reasons, true) || in_array('BILLING_NOT_ACTIVE', $reasons, true)) {
            return new RouteOptimizationUnavailable('Google road routing requires active billing. Ask the administrator to check the Google Cloud billing account.', 'BILLING_DISABLED');
        }
        if ($response->status() === 429 || $status === 'RESOURCE_EXHAUSTED') {
            return new RouteOptimizationUnavailable('The Google road routing quota has been reached. Try later or ask the administrator to review its quota.', 'ROUTES_QUOTA_EXCEEDED');
        }
        if (collect($reasons)->contains(fn (string $reason): bool => str_starts_with($reason, 'API_KEY_')) || in_array($status, ['PERMISSION_DENIED', 'UNAUTHENTICATED'], true)) {
            return new RouteOptimizationUnavailable('Google rejected the server routing key or its restrictions. Ask the administrator to check Routes API access for the server key.', 'ROUTES_ACCESS_DENIED');
        }

        return new RouteOptimizationUnavailable('Google could not calculate a road route. Check the delivery locations and try again.', 'ROUTES_REQUEST_FAILED');
    }

    private function orderConditions(Collection $orders, int $driverId): array
    {
        $orders->loadMissing(['orderItems.product', 'product', 'trip.product', 'trip.latestTelemetry.device']);
        $conditions = [];
        foreach ($orders as $order) {
            if ((int) $order->driver_id !== $driverId) {
                throw ValidationException::withMessages(['orders' => 'A delivery is not assigned to the authenticated driver.']);
            }
            $trip = $order->trip;
            $telemetry = $trip?->latestTelemetry;
            $age = $telemetry?->recorded_at?->diffInSeconds(now(), false);
            $verified = $trip && (int) $trip->driver_id === $driverId
                && in_array($trip->status, ['pending', 'in_progress'], true)
                && $telemetry?->device && (int) $telemetry->device->truck_id === (int) $trip->truck_id
                && $age !== null && $age >= -30 && $age < (int) config('coldtrace.gps_timeout_seconds', 30);
            $temperature = $verified && is_numeric($telemetry->temperature) && is_finite((float) $telemetry->temperature)
                ? (float) $telemetry->temperature : null;
            $products = $order->orderItems->pluck('product')->filter()->unique('id');
            if ($products->isEmpty()) {
                $products = collect([$order->product ?? $trip?->product])->filter();
            }
            $productConditions = $products->map(function ($product) use ($trip, $telemetry, $verified): array {
                // A trip's RSL belongs only to its monitored product, never to
                // every product in the order or every order on the truck.
                $rsl = $verified && (int) $trip->product_id === (int) $product->id
                    && is_numeric($telemetry->rsl_hours) && is_finite((float) $telemetry->rsl_hours)
                        ? (float) $telemetry->rsl_hours : null;

                return [
                    'product_id' => $product->id,
                    'min_temp' => $product->min_temp,
                    'max_temp' => $product->max_temp,
                    'initial_shelf_life_hours' => $product->initial_shelf_life_hours,
                    'rsl_hours' => $rsl,
                ];
            })->values()->all();
            if ($productConditions === []) {
                $productConditions[] = ['product_id' => null, 'min_temp' => null, 'max_temp' => null, 'initial_shelf_life_hours' => null, 'rsl_hours' => null];
            }
            $conditions[$order->id] = [
                'order_id' => $order->id,
                'expected_delivery_at' => $order->expected_delivery_at?->toIso8601String(),
                'deadline_in_minutes' => $order->expected_delivery_at ? now()->diffInSeconds($order->expected_delivery_at, false) / 60 : null,
                'temperature' => $temperature,
                'temperature_source' => $verified ? (str_starts_with((string) $telemetry->device->device_code, 'SIM-') ? 'simulated' : 'sensor') : null,
                'telemetry_recorded_at' => $verified ? $telemetry->recorded_at->toIso8601String() : null,
                'products' => $productConditions,
            ];
        }

        return $conditions;
    }

    private function scoreCandidates(array $candidates, array $conditions): array
    {
        $maxDuration = max(1, ...array_map(fn (array $candidate): float => $this->seconds($candidate['route']['duration']), $candidates));
        $maxDistance = max(1, ...array_map(fn (array $candidate): float => (float) $candidate['route']['distanceMeters'], $candidates));
        $options = [];
        foreach ($candidates as $candidate) {
            $elapsedMinutes = 0;
            $arrivals = [];
            foreach ($candidate['orderedStops'] as $index => $stop) {
                $elapsedMinutes += $this->seconds($candidate['route']['legs'][$index]['duration']) / 60;
                $condition = $conditions[$stop['id']];
                $lateMinutes = $condition['deadline_in_minutes'] !== null ? max(0, $elapsedMinutes - $condition['deadline_in_minutes']) : 0;
                $temperatureRisks = [];
                $rslRisks = [];
                $projections = [];
                foreach ($condition['products'] as $product) {
                    $temperatureRisks[] = $condition['temperature'] === null || $product['min_temp'] === null || $product['max_temp'] === null
                        ? 0.5 : (($condition['temperature'] < $product['min_temp'] || $condition['temperature'] > $product['max_temp']) ? 1.0 : 0.0);
                    $projected = $product['rsl_hours'] !== null ? $product['rsl_hours'] - $elapsedMinutes / 60 : null;
                    $fraction = $projected !== null && $product['initial_shelf_life_hours'] > 0 ? $projected / $product['initial_shelf_life_hours'] : null;
                    $rslRisks[] = $projected === null ? 0.5 : (($projected <= 0 || ($fraction !== null && $fraction <= 0.1)) ? 1.0 : (($fraction !== null && $fraction <= 0.3) ? 0.75 : 0.0));
                    $projections[] = ['product_id' => $product['product_id'], 'projected_rsl_hours_at_arrival' => $projected === null ? null : round($projected, 2)];
                }
                $arrivals[] = [
                    'order_id' => $stop['id'],
                    'arrival_minutes' => round($elapsedMinutes, 2),
                    'late_minutes' => round($lateMinutes, 2),
                    'deadline_risk' => min(1, $lateMinutes / 60),
                    'temperature_risk' => max($temperatureRisks),
                    'rsl_risk' => max($rslRisks),
                    'products' => $projections,
                ];
            }
            // Worst cargo risk matters even if other orders are healthy. Mean
            // risk also rewards getting more vulnerable cargo delivered earlier.
            $aggregate = fn (string $field): float => (max(array_column($arrivals, $field)) + array_sum(array_column($arrivals, $field)) / count($arrivals)) / 2;
            $temperatureRisk = $aggregate('temperature_risk');
            $rslRisk = $aggregate('rsl_risk');
            $deadlineRisk = $aggregate('deadline_risk');
            $worstRisk = max(...array_column($arrivals, 'temperature_risk'), ...array_column($arrivals, 'rsl_risk'));
            $score = 0.25 * $this->seconds($candidate['route']['duration']) / $maxDuration
                + 0.15 * $candidate['route']['distanceMeters'] / $maxDistance
                + 0.20 * $temperatureRisk + 0.25 * $rslRisk + 0.15 * $deadlineRisk;
            $options[] = [
                'route_id' => $candidate['route_id'],
                'eta_minutes' => round($this->seconds($candidate['route']['duration']) / 60, 2),
                'distance_km' => round($candidate['route']['distanceMeters'] / 1000, 2),
                'route_score' => round($score, 4),
                'risk_level' => $worstRisk >= 1 ? 'critical' : (($worstRisk > 0 || $deadlineRisk > 0) ? 'warning' : 'safe'),
                'deliveries' => $arrivals,
            ];
        }
        usort($options, fn (array $a, array $b): int => [$a['route_score'], $a['eta_minutes'], $a['distance_km'], $a['route_id']] <=> [$b['route_score'], $b['eta_minutes'], $b['distance_km'], $b['route_id']]);

        return [
            'planning_scope' => 'All active deliveries assigned to this driver. Arrival times are cumulative Google road travel times; unloading time is not included. Missing cargo readings and limits remain unknown.',
            'server_verified_orders' => array_values($conditions),
            'scoring_weights' => ['eta' => 0.25, 'distance' => 0.15, 'temperature' => 0.20, 'rsl' => 0.25, 'deadline' => 0.15],
            'route_options' => $options,
            'lowest_score_route_id' => $options[0]['route_id'],
        ];
    }
}
