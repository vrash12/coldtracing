@extends('layouts.app')

@section('title', 'My Assigned Orders')

@section('content')

@php
    $routeStops = collect($routeStops ?? []);
    $ordersMissingCoordinates = collect($ordersMissingCoordinates ?? []);

    $routeStopCount = $routeStops->count();
    $missingCoordinateCount = $ordersMissingCoordinates->count();

    $currentGpsText = ($hasCurrentGps ?? false)
        ? number_format((float) $currentLat, 7) . ', ' . number_format((float) $currentLng, 7)
        : 'No live location yet';
@endphp

<div class="ct-index driver-orders-page">

    <header class="ct-index-header driver-orders-header">
        <div>
            <small>Driver workspace</small>
            <h1>Orders & route planner</h1>
            <p>
                Build one efficient route from your truck's current GPS to every active delivery.
            </p>
        </div>

        <div class="ct-index-actions header-actions">
            <button type="button" class="ct-button ct-button-dark primary-button" onclick="optimizeDriverOrdersRoute(true)">
                <i class="bi bi-signpost-split"></i>
                Optimize Route
            </button>
        </div>
    </header>

    @if (session('success'))
        <div class="flash-message success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="flash-message error">
            {{ session('error') }}
        </div>
    @endif

    <section class="route-planner-panel" id="route-planner">
        <div class="route-planner-header">
            <div>
                <span class="section-kicker">Optimized Multi-Stop Navigation</span>
                <h2>Route planner</h2>
                <p>
                    Uses your latest truck location and every active order with saved coordinates.
                </p>
            </div>

            <span class="route-status-badge" id="routeStatusBadge">
                Ready to optimize
            </span>
        </div>

        <div class="route-summary-grid">
            <div class="route-summary-card current-gps-card">
                <span>Current Truck GPS</span>
                <strong id="currentGpsText">{{ $currentGpsText }}</strong>
                <small id="currentGpsHelp">
                    {{ ($hasCurrentGps ?? false)
                        ? (($gpsSource ?? 'esp32') === 'software' ? 'Live device/API fix' : 'Verified ESP32 fix')
                            . (isset($gpsAgeSeconds) ? ' · ' . $gpsAgeSeconds . 's ago' : '')
                        : 'Start the API feed for live device location.' }}
                </small>
            </div>

            <div class="route-summary-card">
                <span>Stops Included</span>
                <strong id="routeStopsCount">{{ $routeStopCount }}</strong>
                <small>{{ $missingCoordinateCount }} active order(s) missing coordinates</small>
            </div>

            <div class="route-summary-card">
                <span>Optimized Distance</span>
                <strong id="optimizedDistance">N/A</strong>
                <small id="optimizedDistanceHelp">Calculated after route optimization.</small>
            </div>

            <div class="route-summary-card">
                <span>Estimated Drive Time</span>
                <strong id="optimizedDuration">N/A</strong>
                <small id="optimizedDurationHelp">Traffic-aware when Google Routes API is available.</small>
            </div>
        </div>

        @if (empty($googleMapsApiKey))
            <div class="warning-box">
                Google Maps API key is missing. Add <strong>GOOGLE_MAPS_API_KEY</strong> to your <strong>.env</strong> file.
                ColdTrace can still create an approximate stop order, but the map and traffic-aware route will not load.
            </div>
        @endif

        @if ($routeStopCount === 0)
            <div class="warning-box">
                No active assigned orders with delivery coordinates are available for route optimization.
            </div>
        @endif

        <div class="route-action-row">
            <button type="button" class="primary-button" onclick="optimizeDriverOrdersRoute(true)">
                <i class="bi bi-magic"></i>
                Optimize All Orders
            </button>

            <button type="button" class="secondary-button software-telemetry-toggle" onclick="useBrowserLocationForRoute()">
                <i class="bi bi-crosshair"></i>
                <span data-start-label="Start Live API Feed" data-stop-label="Stop Live API Feed">Start Live API Feed</span>
            </button>

            <button type="button" class="secondary-button" onclick="fitDriverRouteMap()">
                <i class="bi bi-arrows-fullscreen"></i>
                Center Map
            </button>

            <a
                href="#"
                target="_blank"
                rel="noopener"
                class="secondary-button disabled-link"
                id="openOptimizedMapsButton"
            >
                <i class="bi bi-map"></i>
                Open Google Maps
            </a>        </div>

        <div class="route-message" id="routeMessage">
            Press <strong>Optimize All Orders</strong> to calculate the best sequence for all active assigned deliveries.
        </div>

        <div class="route-layout">
            <div class="map-card">
                <div class="map-toolbar">
                    <div>
                        <strong>Optimized Route Map</strong>
                        <span>Truck GPS to every active delivery destination</span>
                    </div>

                    <div class="map-legend">
                        <span><i class="legend-dot truck"></i> Truck</span>
                        <span><i class="legend-dot stop"></i> Delivery Stop</span>
                    </div>
                </div>

                <div id="driverOrdersRouteMap"></div>
            </div>

            <aside class="optimized-route-card">
                <div class="optimized-route-header">
                    <span>Recommended Sequence</span>
                    <strong id="routeSequenceTitle">Not optimized yet</strong>
                    <small id="routeSequenceSubtitle">The stop order will appear here.</small>
                </div>

                <div class="optimized-stop-list" id="optimizedStopList">
                    @forelse ($routeStops as $index => $stop)
                        <button type="button" class="optimized-stop-card" onclick="focusDeliveryStop({{ (int) $stop['id'] }})">
                            <span class="stop-number">{{ $index + 1 }}</span>

                            <span class="stop-body">
                                <strong>{{ $stop['order_code'] }}</strong>
                                <em>{{ $stop['receiver_name'] }}</em>
                                <small>{{ $stop['address'] }}</small>
                            </span>
                        </button>
                    @empty
                        <div class="empty-route">
                            No delivery stops with coordinates are ready for routing.
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>
    </section>

    @if ($ordersMissingCoordinates->count() > 0)
        <section class="missing-location-panel">
            <div class="missing-location-header">
                <div>
                    <span class="section-kicker warning-text">Needs Location</span>
                    <h2>Orders Not Included in Route</h2>
                    <p>
                        These active orders are assigned to you, but they are missing delivery latitude or longitude.
                        Add coordinates before routing them.
                    </p>
                </div>

                <span>{{ $ordersMissingCoordinates->count() }} order(s)</span>
            </div>

            <div class="missing-location-list">
                @foreach ($ordersMissingCoordinates as $missingOrder)
                    <div class="missing-location-card">
                        <strong>{{ $missingOrder->order_code }}</strong>
                        <span>{{ $missingOrder->receiver?->name ?? 'No receiver' }}</span>
                        <small>{{ $missingOrder->delivery_address }}</small>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel driver-orders-panel">
        <div class="panel-header">
            <div>
                <h2>Assigned Order List</h2>
                <p>Search the full assignment history. Route optimization always uses all active stops, not only this page.</p>
            </div>

            <form method="GET" action="{{ route('driver.orders.index') }}" class="filter-form">
                <div class="search-input-wrap">
                    <i class="bi bi-search"></i>

                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search order, receiver, product, address..."
                    >
                </div>

                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="assigned" {{ $status === 'assigned' ? 'selected' : '' }}>Assigned</option>
                    <option value="in_transit" {{ $status === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                    <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>

                <button type="submit">
                    Filter
                </button>

                @if ($search || $status)
                    <a href="{{ route('driver.orders.index') }}" class="clear-button">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <div class="table-wrapper">
            <table class="driver-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Receiver</th>
                        <th>Products</th>
                        <th>Delivery Location</th>
                        <th>Expected</th>
                        <th>Status</th>
                        <th>Route</th>
                        <th class="action-column">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td data-label="Order">
                                <div class="main-cell">
                                    <strong>{{ $order->order_code }}</strong>
                                    <small>{{ $order->created_at?->format('M d, Y h:i A') }}</small>
                                </div>
                            </td>

                            <td data-label="Receiver">
                                <div class="main-cell">
                                    <strong>{{ $order->receiver?->name ?? 'N/A' }}</strong>
                                    <small>{{ $order->receiver?->phone ?? $order->receiver?->email ?? 'No contact' }}</small>
                                </div>
                            </td>

                            <td data-label="Products">
                                <div class="main-cell">
                                    @forelse ($order->orderItems as $item)
                                        <strong>{{ $item->product?->name ?? 'N/A' }}</strong>
                                        <small>{{ $item->quantity }} {{ $item->unit }}</small>
                                    @empty
                                        <strong>No products</strong>
                                        <small>N/A</small>
                                    @endforelse
                                </div>
                            </td>

                            <td data-label="Delivery Location">
                                <div class="route-cell">
                                    <span>{{ $order->delivery_address }}</span>
                                    <small>
                                        {{ $order->delivery_lat ?? 'No latitude' }},
                                        {{ $order->delivery_lng ?? 'No longitude' }}
                                    </small>
                                </div>
                            </td>

                            <td data-label="Expected">
                                @if ($order->expected_delivery_at)
                                    <div class="date-cell">
                                        <strong>{{ $order->expected_delivery_at->format('M d, Y') }}</strong>
                                        <small>{{ $order->expected_delivery_at->format('h:i A') }}</small>
                                    </div>
                                @else
                                    <span class="muted">Not set</span>
                                @endif
                            </td>

                            <td data-label="Status">
                                <span class="status-badge status-{{ $order->status }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>

                            <td data-label="Route">
                                <span class="route-order-badge" id="routeOrderBadge{{ $order->id }}">
                                    Not routed
                                </span>
                            </td>

                            <td data-label="Action">
                                <a href="{{ route('driver.orders.show', $order) }}" class="primary-button small-button">
                                    <i class="bi bi-arrow-right-circle-fill"></i>Open delivery
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="bi bi-bag"></i>
                                    <strong>No assigned orders yet.</strong>
                                    <span>Orders assigned by the administrator will appear here.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-box">
            {{ $orders->links() }}
        </div>
    </section>

    <nav class="mobile-driver-action-bar" aria-label="Mobile driver route actions">
        <button type="button" onclick="optimizeDriverOrdersRoute(true)">
            <i class="bi bi-magic"></i>
            Optimize
        </button>

        <button type="button" class="software-telemetry-toggle" onclick="useBrowserLocationForRoute()">
            <i class="bi bi-crosshair"></i>
            <span data-start-label="Live Feed" data-stop-label="Stop Feed">Live Feed</span>
        </button>

        <a href="#" target="_blank" rel="noopener" id="mobileOpenMapsButton" class="disabled-link">
            <i class="bi bi-map"></i>
            Maps
        </a>
    </nav>
</div>

@endsection


@push('scripts')
<script>
    const deliveryStops = @json($routeStops->values());
    const initialCurrentPosition = @json(($hasCurrentGps ?? false) ? ['lat' => (float) $currentLat, 'lng' => (float) $currentLng] : null);
    const googleApiKey = @json($googleMapsApiKey ?? '');
    const softwareTelemetryUrl = @json(route('driver.telemetry.software-feed'));
    const csrfToken = @json(csrf_token());

    const MAX_EXHAUSTIVE_ROUTE_STOPS = 10;
    const MAX_ROUTES_API_STOPS = 25;
    const BROWSER_GPS_MAX_ACCURACY_METERS = 200;

    let driverOrdersRouteMap = null;
    let AdvancedMarkerElementClass = null;
    let currentTruckMarker = null;
    let stopMarkers = new Map();
    let routePolyline = null;
    let latestCurrentPosition = initialCurrentPosition;
    let optimizedStops = [...deliveryStops];
    let routeHasBeenOptimized = false;
    let lastRouteResult = null;
    let liveLocationWatchId = null;
    let softwareTelemetryTimer = null;
    let softwareTelemetryPosition = null;
    let softwareTelemetryRequestPending = false;

    document.addEventListener('DOMContentLoaded', function () {
        renderStopList(optimizedStops, {
            title: deliveryStops.length ? 'Waiting for optimization' : 'No stops available',
            subtitle: deliveryStops.length
                ? 'Press Optimize All Orders to calculate the shortest route.'
                : 'No active assigned orders with coordinates are ready.'
        });

        updateMapsButtons(null);
        setCurrentGpsCardState();
    });

    function setRouteBadge(text, state) {
        const badge = document.getElementById('routeStatusBadge');

        if (!badge) {
            return;
        }

        badge.classList.remove('success', 'warning', 'error');
        badge.innerText = text;

        if (state) {
            badge.classList.add(state);
        }
    }

    function setRouteMessage(message, state = null) {
        const element = document.getElementById('routeMessage');

        if (!element) {
            return;
        }

        element.classList.remove('success', 'warning');
        element.innerHTML = message;

        if (state) {
            element.classList.add(state);
        }
    }

    function setCurrentGpsCardState() {
        const card = document.querySelector('.current-gps-card');

        if (!card) {
            return;
        }

        card.classList.toggle('waiting', !latestCurrentPosition);
    }

    function updateCurrentGpsDisplay(position, sourceText) {
        const latitude = Number(position.lat);
        const longitude = Number(position.lng);

        if (!isUsableCoordinate(latitude, longitude)) {
            return false;
        }

        latestCurrentPosition = {
            lat: latitude,
            lng: longitude,
        };

        const gpsText = latestCurrentPosition.lat.toFixed(7) + ', ' + latestCurrentPosition.lng.toFixed(7);

        const textElement = document.getElementById('currentGpsText');
        const helpElement = document.getElementById('currentGpsHelp');

        if (textElement) {
            textElement.innerText = gpsText;
        }

        if (helpElement) {
            helpElement.innerText = sourceText || 'Using latest current location.';
        }

        setCurrentGpsCardState();
        updateCurrentTruckMarker();

        return true;
    }

    function isUsableCoordinate(latitude, longitude) {
        return Number.isFinite(latitude)
            && Number.isFinite(longitude)
            && latitude >= -90
            && latitude <= 90
            && longitude >= -180
            && longitude <= 180
            && !(latitude === 0 && longitude === 0);
    }

    function updateSoftwareTelemetryButton() {
        const running = softwareTelemetryTimer !== null;

        document.querySelectorAll('.software-telemetry-toggle').forEach(function (button) {
            const label = button.querySelector('span');
            const icon = button.querySelector('i');

            if (label) {
                label.innerText = running
                    ? label.dataset.stopLabel
                    : label.dataset.startLabel;
            }

            if (icon) {
                icon.className = running ? 'bi bi-stop-circle' : 'bi bi-crosshair';
            }
        });
    }

    function rememberAccurateDevicePosition(position) {
        const accuracy = Number(position?.coords?.accuracy);
        const latitude = Number(position?.coords?.latitude);
        const longitude = Number(position?.coords?.longitude);

        if (!isUsableCoordinate(latitude, longitude)
            || !Number.isFinite(accuracy)
            || accuracy <= 0
            || accuracy > BROWSER_GPS_MAX_ACCURACY_METERS) {
            return false;
        }

        softwareTelemetryPosition = {
            latitude,
            longitude,
            accuracy,
        };

        updateCurrentGpsDisplay({
            lat: latitude,
            lng: longitude,
        }, `Live device location · estimated accuracy ±${Math.round(accuracy)} m.`);

        return true;
    }

    async function publishSoftwareTelemetry() {
        if (softwareTelemetryRequestPending) {
            return;
        }

        softwareTelemetryRequestPending = true;

        const payload = softwareTelemetryPosition
            ? {
                latitude: softwareTelemetryPosition.latitude,
                longitude: softwareTelemetryPosition.longitude,
                accuracy_meters: softwareTelemetryPosition.accuracy,
            }
            : {};

        try {
            const response = await fetch(softwareTelemetryUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'The software telemetry API rejected the update.');
            }

            const temperature = Number(result.data?.temperature);
            const temperatureText = Number.isFinite(temperature)
                ? `${temperature.toFixed(2)} °C simulated temperature`
                : 'simulated temperature stored';
            const locationText = result.data?.location_accepted
                ? `live location ±${Math.round(Number(result.data.accuracy_meters))} m`
                : 'waiting for an accurate device location';

            setRouteBadge('API feed live', 'success');
            setRouteMessage(`Software demo feed is active: ${temperatureText}; ${locationText}. Updates are saved every 5 seconds.`, 'success');
        } catch (error) {
            console.error('Software telemetry update failed:', error);
            setRouteBadge('API feed error', 'error');
            setRouteMessage(error.message || 'The software telemetry API could not save an update.', 'warning');
        } finally {
            softwareTelemetryRequestPending = false;
        }
    }

    function startSoftwareTelemetryFeed(initialPosition = null) {
        if (softwareTelemetryTimer !== null) {
            if (initialPosition) {
                rememberAccurateDevicePosition(initialPosition);
            }

            return;
        }

        if (initialPosition) {
            rememberAccurateDevicePosition(initialPosition);
        }

        publishSoftwareTelemetry();
        softwareTelemetryTimer = window.setInterval(publishSoftwareTelemetry, 5000);

        if (navigator.geolocation) {
            liveLocationWatchId = navigator.geolocation.watchPosition(
                function (position) {
                    if (!rememberAccurateDevicePosition(position)) {
                        const accuracy = Number(position?.coords?.accuracy);
                        const accuracyText = Number.isFinite(accuracy)
                            ? ` The current estimate is only ±${Math.round(accuracy)} metres.`
                            : '';

                        setRouteBadge('Temperature feed live', 'warning');
                        setRouteMessage(`Simulated temperature is updating, but the location is not accurate enough.${accuracyText}`, 'warning');
                    }
                },
                function (error) {
                    console.warn('Continuous browser location unavailable:', error);

                    if (error.code === error.PERMISSION_DENIED) {
                        setRouteBadge('Temperature feed live', 'warning');
                        setRouteMessage('Simulated temperature is updating every 5 seconds. Enable precise location permission to include live GPS.', 'warning');
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                }
            );
        }

        updateSoftwareTelemetryButton();
    }

    function stopSoftwareTelemetryFeed() {
        if (softwareTelemetryTimer !== null) {
            window.clearInterval(softwareTelemetryTimer);
            softwareTelemetryTimer = null;
        }

        if (liveLocationWatchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(liveLocationWatchId);
            liveLocationWatchId = null;
        }

        softwareTelemetryPosition = null;
        updateSoftwareTelemetryButton();
        setRouteBadge('API feed stopped', 'warning');
        setRouteMessage('The software telemetry feed is stopped. No simulated temperature or device location updates are being saved.', 'warning');
    }

    function useBrowserLocationForRoute() {
        if (softwareTelemetryTimer !== null) {
            stopSoftwareTelemetryFeed();
            return;
        }

        startSoftwareTelemetryFeed();
        setRouteBadge('Starting API feed', 'warning');
        setRouteMessage(navigator.geolocation
            ? 'Simulated temperature is starting now while this device searches for a precise live location.'
            : 'Simulated temperature is starting now. This browser cannot provide live location.', 'warning');
    }

    async function optimizeDriverOrdersRoute(shouldFocus) {
        if (!deliveryStops.length) {
            setRouteBadge('No stops', 'warning');
            setRouteMessage('There are no active assigned orders with delivery coordinates to optimize.', 'warning');
            return;
        }

        if (!latestCurrentPosition) {
            setRouteBadge('GPS needed', 'warning');
            setRouteMessage('ColdTrace needs a recent truck position before it can calculate the route. Wait for ESP32 GPS or tap <strong>Use Device Location</strong>.', 'warning');
            return;
        }

        setRouteBadge('Optimizing...', 'warning');
        setRouteMessage('Calculating the shortest route across all assigned delivery locations...', 'warning');

        try {
            let result = null;

            if (googleApiKey && deliveryStops.length <= MAX_EXHAUSTIVE_ROUTE_STOPS) {
                result = await optimizeWithRoutesApiExhaustive(latestCurrentPosition, deliveryStops);
            } else if (googleApiKey && deliveryStops.length <= MAX_ROUTES_API_STOPS) {
                result = await optimizeWithNearestNeighborAndRoutesApi(latestCurrentPosition, deliveryStops);
            }

            if (!result) {
                result = buildNearestNeighborFallback(latestCurrentPosition, deliveryStops);
            }

            applyOptimizedRouteResult(result, shouldFocus);
        } catch (error) {
            console.error('Route optimization failed:', error);
            const fallback = buildNearestNeighborFallback(latestCurrentPosition, deliveryStops);
            applyOptimizedRouteResult(fallback, shouldFocus);
            setRouteMessage('Google Routes API could not complete the route, so ColdTrace used an approximate nearest-stop sequence.', 'warning');
        }
    }

    async function optimizeWithRoutesApiExhaustive(origin, stops) {
        let bestResult = null;
        let bestScore = Infinity;

        for (let destinationIndex = 0; destinationIndex < stops.length; destinationIndex += 1) {
            const destinationStop = stops[destinationIndex];
            const intermediateStops = stops.filter(function (_, index) {
                return index !== destinationIndex;
            });

            const route = await fetchRoutesApiRoute(origin, destinationStop, intermediateStops, true);

            if (!route) {
                continue;
            }

            const optimizedIntermediateIndexes = route.optimizedIntermediateWaypointIndex ?? [];
            const orderedIntermediates = optimizedIntermediateIndexes.length
                ? optimizedIntermediateIndexes.map(function (index) {
                    return intermediateStops[index];
                })
                : intermediateStops;

            const orderedStops = [...orderedIntermediates, destinationStop];
            const score = getRouteDurationSeconds(route) + ((route.distanceMeters ?? 0) * 0.04);

            if (score < bestScore) {
                bestScore = score;
                bestResult = {
                    orderedStops: orderedStops,
                    route: route,
                    strategy: 'traffic-aware optimized route',
                };
            }
        }

        return bestResult;
    }

    async function optimizeWithNearestNeighborAndRoutesApi(origin, stops) {
        const orderedStops = nearestNeighborOrder(origin, stops);
        const destinationStop = orderedStops[orderedStops.length - 1];
        const intermediateStops = orderedStops.slice(0, -1);
        const route = await fetchRoutesApiRoute(origin, destinationStop, intermediateStops, false);

        if (!route) {
            return null;
        }

        return {
            orderedStops: orderedStops,
            route: route,
            strategy: 'nearest-stop order with traffic-aware road route',
        };
    }

    async function fetchRoutesApiRoute(origin, destinationStop, intermediateStops, optimizeWaypointOrder) {
        const body = {
            origin: {
                location: {
                    latLng: {
                        latitude: Number(origin.lat),
                        longitude: Number(origin.lng),
                    }
                }
            },
            destination: {
                location: {
                    latLng: {
                        latitude: Number(destinationStop.lat),
                        longitude: Number(destinationStop.lng),
                    }
                }
            },
            intermediates: intermediateStops.map(function (stop) {
                return {
                    location: {
                        latLng: {
                            latitude: Number(stop.lat),
                            longitude: Number(stop.lng),
                        }
                    }
                };
            }),
            travelMode: 'DRIVE',
            routingPreference: 'TRAFFIC_AWARE',
            computeAlternativeRoutes: false,
            units: 'METRIC',
            languageCode: 'en-US',
        };

        if (optimizeWaypointOrder && intermediateStops.length > 0) {
            body.optimizeWaypointOrder = true;
        }

        const response = await fetch('https://routes.googleapis.com/directions/v2:computeRoutes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Goog-Api-Key': googleApiKey,
                'X-Goog-FieldMask': [
                    'routes.distanceMeters',
                    'routes.duration',
                    'routes.staticDuration',
                    'routes.polyline.encodedPolyline',
                    'routes.localizedValues',
                    'routes.optimizedIntermediateWaypointIndex'
                ].join(',')
            },
            body: JSON.stringify(body),
        });

        const result = await response.json();

        if (!response.ok || !result.routes || result.routes.length === 0) {
            console.error('Routes API error:', result);
            return null;
        }

        return result.routes[0];
    }

    function buildNearestNeighborFallback(origin, stops) {
        const orderedStops = nearestNeighborOrder(origin, stops);
        const approximateMeters = calculateApproximateRouteMeters(origin, orderedStops);

        return {
            orderedStops: orderedStops,
            route: null,
            approximateMeters: approximateMeters,
            strategy: googleApiKey
                ? 'approximate nearest-stop route'
                : 'approximate nearest-stop route without Google Routes API',
        };
    }

    function nearestNeighborOrder(origin, stops) {
        const remaining = stops.map(function (stop) {
            return { ...stop };
        });

        const ordered = [];
        let current = {
            lat: Number(origin.lat),
            lng: Number(origin.lng),
        };

        while (remaining.length) {
            let bestIndex = 0;
            let bestDistance = Infinity;

            remaining.forEach(function (stop, index) {
                const distance = haversineMeters(current, stop);

                if (distance < bestDistance) {
                    bestDistance = distance;
                    bestIndex = index;
                }
            });

            const next = remaining.splice(bestIndex, 1)[0];
            ordered.push(next);
            current = {
                lat: Number(next.lat),
                lng: Number(next.lng),
            };
        }

        return ordered;
    }

    function calculateApproximateRouteMeters(origin, stops) {
        if (!origin || !stops.length) {
            return 0;
        }

        let total = 0;
        let current = origin;

        stops.forEach(function (stop) {
            total += haversineMeters(current, stop);
            current = stop;
        });

        return total;
    }

    function haversineMeters(a, b) {
        const radius = 6371000;
        const lat1 = toRadians(Number(a.lat));
        const lat2 = toRadians(Number(b.lat));
        const deltaLat = toRadians(Number(b.lat) - Number(a.lat));
        const deltaLng = toRadians(Number(b.lng) - Number(a.lng));

        const sinLat = Math.sin(deltaLat / 2);
        const sinLng = Math.sin(deltaLng / 2);

        const value = sinLat * sinLat
            + Math.cos(lat1) * Math.cos(lat2) * sinLng * sinLng;

        return radius * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
    }

    function toRadians(value) {
        return value * Math.PI / 180;
    }

    function applyOptimizedRouteResult(result, shouldFocus) {
        optimizedStops = result.orderedStops;
        routeHasBeenOptimized = true;
        lastRouteResult = result;

        renderStopList(optimizedStops, {
            title: 'Optimized route ready',
            subtitle: 'Strategy: ' + result.strategy,
        });

        updateTableRouteBadges(optimizedStops);
        updateMapsButtons(buildGoogleMapsMultiStopUrl(optimizedStops));
        updateRouteNumbersOnMap(optimizedStops);

        if (result.route) {
            drawRoutesApiPolyline(result.route, optimizedStops, shouldFocus);
            updateRouteStatsFromRoute(result.route, result.strategy);
        } else {
            drawStraightLineRoute(optimizedStops, shouldFocus);
            updateRouteStatsFromApproximation(result.approximateMeters, result.strategy);
        }
    }

    function updateRouteStatsFromRoute(route, strategy) {
        const distanceText = getRouteDistanceText(route);
        const durationText = getRouteDurationText(route);

        document.getElementById('optimizedDistance').innerText = distanceText;
        document.getElementById('optimizedDuration').innerText = durationText;
        document.getElementById('optimizedDistanceHelp').innerText = 'Road distance from Google Routes API.';
        document.getElementById('optimizedDurationHelp').innerText = 'Traffic-aware drive time.';

        setRouteBadge('Optimized', 'success');
        setRouteMessage('Route optimized using ' + escapeHtml(strategy) + '. Open Google Maps to start navigation.', 'success');
    }

    function updateRouteStatsFromApproximation(approximateMeters, strategy) {
        document.getElementById('optimizedDistance').innerText = metersToText(approximateMeters);
        document.getElementById('optimizedDuration').innerText = 'Open Maps';
        document.getElementById('optimizedDistanceHelp').innerText = 'Approximate straight-line order. Google Maps will calculate road navigation.';
        document.getElementById('optimizedDurationHelp').innerText = 'Drive time appears in Google Maps.';

        setRouteBadge('Approximate route', 'warning');
        setRouteMessage('ColdTrace used an approximate nearest-location sequence because a full Routes API optimization was unavailable. Open Google Maps for road navigation.', 'warning');
    }

    function renderStopList(stops, meta) {
        const title = document.getElementById('routeSequenceTitle');
        const subtitle = document.getElementById('routeSequenceSubtitle');
        const container = document.getElementById('optimizedStopList');

        if (title) {
            title.innerText = meta?.title || 'Recommended sequence';
        }

        if (subtitle) {
            subtitle.innerText = meta?.subtitle || '';
        }

        if (!container) {
            return;
        }

        if (!stops.length) {
            container.innerHTML = '<div class="empty-route">No delivery stops with coordinates are ready for routing.</div>';
            return;
        }

        container.innerHTML = stops.map(function (stop, index) {
            return `
                <button type="button" class="optimized-stop-card" onclick="focusDeliveryStop(${Number(stop.id)})">
                    <span class="stop-number">${index + 1}</span>
                    <span class="stop-body">
                        <strong>${escapeHtml(stop.order_code)}</strong>
                        <em>${escapeHtml(stop.receiver_name || 'N/A')}</em>
                        <small>${escapeHtml(stop.address || 'No address')}</small>
                        <small>${escapeHtml((stop.products || []).join(', ') || 'No products listed')}</small>
                        <span class="stop-actions-inline">
                            <a href="${escapeAttribute(stop.url || '#')}" class="stop-mini-action" onclick="event.stopPropagation();">View</a>
                        </span>
                    </span>
                </button>
            `;
        }).join('');
    }

    function updateTableRouteBadges(stops) {
        document.querySelectorAll('.route-order-badge').forEach(function (badge) {
            badge.classList.remove('active', 'next');
            badge.innerText = 'Not in route';
        });

        stops.forEach(function (stop, index) {
            const badge = document.getElementById('routeOrderBadge' + stop.id);

            if (!badge) {
                return;
            }

            badge.classList.add(index === 0 ? 'next' : 'active');
            badge.innerText = index === 0 ? 'Next stop' : 'Stop ' + (index + 1);
        });
    }

    function updateMapsButtons(url) {
        const buttons = [
            document.getElementById('openOptimizedMapsButton'),
            document.getElementById('mobileOpenMapsButton'),
        ];

        buttons.forEach(function (button) {
            if (!button) {
                return;
            }

            if (!url) {
                button.setAttribute('href', '#');
                button.classList.add('disabled-link');
                return;
            }

            button.setAttribute('href', url);
            button.classList.remove('disabled-link');
        });
    }

    function buildGoogleMapsMultiStopUrl(stops) {
        if (!stops.length) {
            return null;
        }

        const destination = stops[stops.length - 1];
        const waypoints = stops.slice(0, -1)
            .map(function (stop) {
                return Number(stop.lat) + ',' + Number(stop.lng);
            })
            .join('|');

        const url = new URL('https://www.google.com/maps/dir/');
        url.searchParams.set('api', '1');
        url.searchParams.set('travelmode', 'driving');

        if (latestCurrentPosition) {
            url.searchParams.set('origin', latestCurrentPosition.lat + ',' + latestCurrentPosition.lng);
        }

        url.searchParams.set('destination', Number(destination.lat) + ',' + Number(destination.lng));

        if (waypoints) {
            url.searchParams.set('waypoints', waypoints);
        }

        return url.toString();
    }

    function getRouteDurationSeconds(route) {
        return parseGoogleDuration(route.duration) ?? parseGoogleDuration(route.staticDuration) ?? 0;
    }

    function parseGoogleDuration(duration) {
        if (!duration) {
            return null;
        }

        return Number(String(duration).replace('s', ''));
    }

    function getRouteDurationText(route) {
        return route.localizedValues?.duration?.text
            ?? secondsToText(getRouteDurationSeconds(route))
            ?? 'N/A';
    }

    function getRouteDistanceText(route) {
        return route.localizedValues?.distance?.text
            ?? metersToText(route.distanceMeters)
            ?? 'N/A';
    }

    function secondsToText(seconds) {
        if (!seconds) {
            return null;
        }

        const minutes = Math.round(seconds / 60);

        if (minutes < 60) {
            return minutes + ' min';
        }

        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;

        return hours + ' hr ' + remainingMinutes + ' min';
    }

    function metersToText(meters) {
        if (!meters) {
            return 'N/A';
        }

        if (meters < 1000) {
            return Math.round(meters) + ' m';
        }

        return (meters / 1000).toFixed(1) + ' km';
    }

    function decodePolyline(encoded) {
        let index = 0;
        let lat = 0;
        let lng = 0;
        const coordinates = [];

        while (index < encoded.length) {
            let b;
            let shift = 0;
            let result = 0;

            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while (b >= 0x20);

            const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lat += dlat;

            shift = 0;
            result = 0;

            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while (b >= 0x20);

            const dlng = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lng += dlng;

            coordinates.push({
                lat: lat / 1e5,
                lng: lng / 1e5
            });
        }

        return coordinates;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    async function initDriverOrdersRouteMap() {
        const mapElement = document.getElementById('driverOrdersRouteMap');

        if (!mapElement || !window.google || !google.maps) {
            return;
        }

        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([
            google.maps.importLibrary('maps'),
            google.maps.importLibrary('marker'),
        ]);

        AdvancedMarkerElementClass = AdvancedMarkerElement;

        const fallbackCenter = latestCurrentPosition || (deliveryStops[0]
            ? { lat: Number(deliveryStops[0].lat), lng: Number(deliveryStops[0].lng) }
            : { lat: 14.5995, lng: 120.9842 });

        driverOrdersRouteMap = new Map(mapElement, {
            center: fallbackCenter,
            zoom: 12,
            gestureHandling: 'greedy',
            scrollwheel: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            mapId: 'DEMO_MAP_ID',
        });

        updateRouteNumbersOnMap(optimizedStops);

        if (lastRouteResult) {
            if (lastRouteResult.route) {
                drawRoutesApiPolyline(lastRouteResult.route, lastRouteResult.orderedStops, false);
            } else {
                drawStraightLineRoute(lastRouteResult.orderedStops, false);
            }
        }

        fitDriverRouteMap();
    }

    function createMarkerContent(label, type) {
        if (type === 'truck') {
            return window.createColdTraceTruckMarker(@json(auth()->user()->assignedTruck?->id));
        }
        const marker = document.createElement('div');
        marker.style.width = '38px';
        marker.style.height = '38px';
        marker.style.borderRadius = '14px';
        marker.style.display = 'flex';
        marker.style.alignItems = 'center';
        marker.style.justifyContent = 'center';
        marker.style.color = '#ffffff';
        marker.style.fontWeight = '950';
        marker.style.fontSize = '14px';
        marker.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.26)';
        marker.style.border = '3px solid #ffffff';
        marker.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
        marker.innerText = label;

        return marker;
    }

    function updateCurrentTruckMarker() {
        if (!driverOrdersRouteMap || !AdvancedMarkerElementClass || !latestCurrentPosition) {
            return;
        }

        if (!currentTruckMarker) {
            currentTruckMarker = new AdvancedMarkerElementClass({
                map: driverOrdersRouteMap,
                position: latestCurrentPosition,
                title: 'Current Truck GPS',
                content: createMarkerContent('TRK', 'truck'),
            });
        } else {
            currentTruckMarker.position = latestCurrentPosition;
        }
    }

    function updateRouteNumbersOnMap(stops) {
        if (!driverOrdersRouteMap || !AdvancedMarkerElementClass) {
            return;
        }

        stopMarkers.forEach(function (marker) {
            marker.map = null;
        });

        stopMarkers = new Map();
        updateCurrentTruckMarker();

        stops.forEach(function (stop, index) {
            const position = {
                lat: Number(stop.lat),
                lng: Number(stop.lng),
            };

            const marker = new AdvancedMarkerElementClass({
                map: driverOrdersRouteMap,
                position: position,
                title: (index + 1) + '. ' + stop.order_code,
                content: createMarkerContent(String(index + 1), 'stop'),
            });

            stopMarkers.set(Number(stop.id), marker);
        });
    }

    function focusDeliveryStop(stopId) {
        const marker = stopMarkers.get(Number(stopId));
        const stop = optimizedStops.find(function (item) {
            return Number(item.id) === Number(stopId);
        });

        if (!driverOrdersRouteMap || !stop) {
            return;
        }

        driverOrdersRouteMap.panTo({
            lat: Number(stop.lat),
            lng: Number(stop.lng),
        });
        driverOrdersRouteMap.setZoom(15);

        if (marker) {
            marker.content.animate?.([
                { transform: 'scale(1)' },
                { transform: 'scale(1.18)' },
                { transform: 'scale(1)' }
            ], {
                duration: 450,
                iterations: 1,
            });
        }
    }

    function fitDriverRouteMap() {
        if (!driverOrdersRouteMap || !window.google || !google.maps) {
            return;
        }

        const bounds = new google.maps.LatLngBounds();
        let hasBounds = false;

        if (latestCurrentPosition) {
            bounds.extend(latestCurrentPosition);
            hasBounds = true;
        }

        optimizedStops.forEach(function (stop) {
            bounds.extend({
                lat: Number(stop.lat),
                lng: Number(stop.lng),
            });
            hasBounds = true;
        });

        if (hasBounds) {
            driverOrdersRouteMap.fitBounds(bounds);
        }
    }

    function drawRoutesApiPolyline(route, orderedStops, shouldFocus) {
        if (!driverOrdersRouteMap || !window.google || !google.maps || !route?.polyline?.encodedPolyline) {
            drawStraightLineRoute(orderedStops, shouldFocus);
            return;
        }

        if (routePolyline) {
            routePolyline.setMap(null);
        }

        const decodedPath = decodePolyline(route.polyline.encodedPolyline);

        routePolyline = new google.maps.Polyline({
            path: decodedPath,
            geodesic: true,
            strokeOpacity: 1,
            strokeWeight: 6,
            map: driverOrdersRouteMap,
        });

        if (shouldFocus) {
            fitPolylineBounds(decodedPath);
        }
    }

    function drawStraightLineRoute(orderedStops, shouldFocus) {
        if (!driverOrdersRouteMap || !window.google || !google.maps) {
            return;
        }

        if (routePolyline) {
            routePolyline.setMap(null);
        }

        const path = [];

        if (latestCurrentPosition) {
            path.push(latestCurrentPosition);
        }

        orderedStops.forEach(function (stop) {
            path.push({
                lat: Number(stop.lat),
                lng: Number(stop.lng),
            });
        });

        routePolyline = new google.maps.Polyline({
            path: path,
            geodesic: true,
            strokeOpacity: 0.85,
            strokeWeight: 5,
            map: driverOrdersRouteMap,
        });

        if (shouldFocus) {
            fitPolylineBounds(path);
        }
    }

    function fitPolylineBounds(path) {
        if (!driverOrdersRouteMap || !window.google || !google.maps || !path.length) {
            return;
        }

        const bounds = new google.maps.LatLngBounds();

        path.forEach(function (point) {
            bounds.extend(point);
        });

        driverOrdersRouteMap.fitBounds(bounds);
    }

    window.initDriverOrdersRouteMap = initDriverOrdersRouteMap;
    window.optimizeDriverOrdersRoute = optimizeDriverOrdersRoute;
    window.useBrowserLocationForRoute = useBrowserLocationForRoute;
    window.fitDriverRouteMap = fitDriverRouteMap;
    window.focusDeliveryStop = focusDeliveryStop;
</script>

@if (!empty($googleMapsApiKey))
<script
    async
    defer
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&callback=initDriverOrdersRouteMap&loading=async"
></script>
@endif

@if (!empty($mqttBroker) && !empty($mqttUsername) && !empty($mqttPassword))
<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script>
    const mqttBroker = @json($mqttBroker);
    const mqttOptions = {
        username: @json($mqttUsername),
        password: @json($mqttPassword),
        clean: true,
        reconnectPeriod: 3000,
        connectTimeout: 30000,
        clientId: 'coldtrace-driver-orders-index-' + Math.random().toString(16).substring(2, 10),
    };

    const subscribeTopic = @json($expectedTopic ?: 'coldtrace/trucks/+/telemetry');
    const expectedDeviceCode = @json($expectedDeviceCode);

    function shouldAcceptDriverPayload(data) {
        // With no device paired to this driver's truck there is no reading that
        // legitimately belongs to them, so nothing on the wildcard topic is
        // accepted. Showing another truck's position would be worse than none.
        if (!expectedDeviceCode) {
            return false;
        }

        return data.device_code === expectedDeviceCode;
    }

    function handleDriverTelemetryPayload(data) {
        if (!shouldAcceptDriverPayload(data)) {
            return;
        }

        if (data.latitude === null || data.latitude === undefined
            || data.longitude === null || data.longitude === undefined) {
            return;
        }

        const latitude = Number(data.latitude);
        const longitude = Number(data.longitude);
        const satellites = data.satellites === undefined ? null : Number(data.satellites);
        const hdop = data.hdop === undefined ? null : Number(data.hdop);

        if (data.gps_valid !== true || !isUsableCoordinate(latitude, longitude)) {
            return;
        }

        if ((satellites !== null && (!Number.isFinite(satellites) || satellites < 4))
            || (hdop !== null && (!Number.isFinite(hdop) || hdop > 5))) {
            return;
        }

        const qualityParts = ['Live verified ESP32 GPS'];

        if (satellites !== null) {
            qualityParts.push(`${satellites} satellites`);
        }

        if (hdop !== null) {
            qualityParts.push(`HDOP ${hdop.toFixed(1)}`);
        }

        updateCurrentGpsDisplay({
            lat: latitude,
            lng: longitude,
        }, `${qualityParts.join(' · ')}.`);

        if (routeHasBeenOptimized) {
            updateMapsButtons(buildGoogleMapsMultiStopUrl(optimizedStops));
        }
    }

    const mqttClient = mqtt.connect(mqttBroker, mqttOptions);

    mqttClient.on('connect', function () {
        console.log('ColdTrace driver orders MQTT connected.');

        mqttClient.subscribe(subscribeTopic, function (error) {
            if (error) {
                console.error('MQTT subscribe error:', error);
            }
        });
    });

    mqttClient.on('message', function (topic, message) {
        try {
            const data = JSON.parse(message.toString());
            handleDriverTelemetryPayload(data);
        } catch (error) {
            console.error('Invalid MQTT telemetry payload:', error);
        }
    });

    mqttClient.on('error', function (error) {
        console.error('MQTT connection error:', error);
    });
</script>
@endif
@endpush
