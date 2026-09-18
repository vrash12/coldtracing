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
            <button type="button" class="ct-button ct-button-dark primary-button" data-optimize-orders onclick="optimizeDriverOrdersRoute(true)">
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
                The map is not configured. Ask your administrator to set up Google Maps for this system.
            </div>
        @endif

        @if ($routeStopCount === 0)
            <div class="warning-box">
                No active assigned orders with delivery coordinates are available for route optimization.
            </div>
        @endif

        <div class="route-action-row">
            <button type="button" class="primary-button" data-optimize-orders onclick="optimizeDriverOrdersRoute(true)">
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
                <span data-maps-label>Open Google Maps</span>
            </a>        </div>

        <div class="route-message" id="routeMessage">
            Press <strong>Optimize All Orders</strong> to calculate the best sequence for all active assigned deliveries.
        </div>

        <x-route-speech />

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
        <button type="button" data-optimize-orders onclick="optimizeDriverOrdersRoute(true)">
            <i class="bi bi-magic"></i>
            Optimize
        </button>

        <button type="button" class="software-telemetry-toggle" onclick="useBrowserLocationForRoute()">
            <i class="bi bi-crosshair"></i>
            <span data-start-label="Live Feed" data-stop-label="Stop Feed">Live Feed</span>
        </button>

        <a href="#" target="_blank" rel="noopener" id="mobileOpenMapsButton" class="disabled-link">
            <i class="bi bi-map"></i>
            <span data-maps-label>Maps</span>
        </a>
    </nav>
</div>

@endsection


@push('scripts')
<script>
    const deliveryStops = @json($routeStops->values());
    const initialCurrentPosition = @json(($hasCurrentGps ?? false) ? ['lat' => (float) $currentLat, 'lng' => (float) $currentLng] : null);
    const routeOptimizationUrl = @json(route('driver.orders.optimize'));
    const softwareTelemetryUrl = @json(route('driver.telemetry.software-feed'));
    const csrfToken = @json(csrf_token());

    const BROWSER_GPS_MAX_ACCURACY_METERS = 200;

    let driverOrdersRouteMap = null;
    let AdvancedMarkerElementClass = null;
    let currentTruckMarker = null;
    let stopMarkers = new Map();
    let routePolyline = null;
    let latestCurrentPosition = initialCurrentPosition;
    let currentGpsRecordedAt = ColdTraceLocation.time(@json($gpsRecordedAt ?? null));
    let currentGpsSource = @json($gpsSource ?? null);
    let locationGeneration = 0;
    let optimizationRequestVersion = 0;
    let optimizationController = null;

    function expireDriverLocation(force = false) {
        if (!force && ColdTraceLocation.fresh(currentGpsRecordedAt)) return;
        if (!latestCurrentPosition && !currentTruckMarker) return;
        latestCurrentPosition = null;
        currentTruckMarker && (currentTruckMarker.map = null);
        currentTruckMarker = null;
        locationGeneration++;
        cancelRouteOptimization();
        clearOptimizedRoute();
        document.getElementById('optimizedDistance').innerText = '—';
        document.getElementById('optimizedDuration').innerText = '—';
        document.getElementById('optimizedDistanceHelp').innerText = 'Waiting for live GPS.';
        document.getElementById('optimizedDurationHelp').innerText = 'Waiting for live GPS.';
        document.getElementById('currentGpsText').innerText = 'No live GPS';
        document.getElementById('currentGpsHelp').innerText = 'Truck disconnected or GPS unavailable. Waiting for a fresh location.';
        setCurrentGpsCardState();
        updateMapsButtons(null);
        setRouteBadge('Waiting for live GPS', 'warning');
        setRouteMessage('The last location expired. Routing will be available when a fresh location arrives.', 'warning');
    }
    const driverLocationTimer = setInterval(expireDriverLocation, 1000);
    document.addEventListener('visibilitychange', () => expireDriverLocation());
    window.addEventListener('pagehide', () => {
        cancelRouteOptimization();
        clearInterval(driverLocationTimer);
        if (softwareTelemetryTimer !== null) clearInterval(softwareTelemetryTimer);
        if (liveLocationWatchId !== null) navigator.geolocation?.clearWatch(liveLocationWatchId);
    });
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
                ? 'Press Optimize All Orders to compare road routes and get a recommendation.'
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

    function updateCurrentGpsDisplay(position, sourceText, recordedAt, source) {
        const latitude = Number(position.lat);
        const longitude = Number(position.lng);

        if (!isUsableCoordinate(latitude, longitude) || !ColdTraceLocation.fresh(recordedAt)
            || recordedAt < currentGpsRecordedAt) {
            return false;
        }
        currentGpsRecordedAt = recordedAt;
        currentGpsSource = source;

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
            || accuracy > BROWSER_GPS_MAX_ACCURACY_METERS
            || !ColdTraceLocation.fresh(position?.timestamp)) {
            return false;
        }

        softwareTelemetryPosition = {
            latitude,
            longitude,
            accuracy,
            recordedAt: position.timestamp,
        };

        updateCurrentGpsDisplay({
            lat: latitude,
            lng: longitude,
        }, `Live device location · estimated accuracy ±${Math.round(accuracy)} m.`, position.timestamp, 'software');

        return true;
    }

    async function publishSoftwareTelemetry() {
        if (softwareTelemetryRequestPending) {
            return;
        }

        softwareTelemetryRequestPending = true;

        const payload = softwareTelemetryPosition && ColdTraceLocation.fresh(softwareTelemetryPosition.recordedAt)
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
        if (currentGpsSource === 'software') expireDriverLocation(true);
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

    function setOptimizationBusy(busy) {
        document.querySelectorAll('[data-optimize-orders]').forEach(button => {
            button.disabled = busy;
            button.setAttribute('aria-busy', String(busy));
        });
    }

    function cancelRouteOptimization() {
        optimizationRequestVersion++;
        optimizationController?.abort();
        optimizationController = null;
        setOptimizationBusy(false);
    }

    function clearOptimizedRoute() {
        window.ColdTraceSpeech?.clear();
        routePolyline?.setMap(null);
        routePolyline = null;
        routeHasBeenOptimized = false;
        lastRouteResult = null;
        optimizedStops = [...deliveryStops];
        document.getElementById('optimizedDistance').innerText = '—';
        document.getElementById('optimizedDuration').innerText = '—';
        document.getElementById('optimizedDistanceHelp').innerText = 'No road route calculated.';
        document.getElementById('optimizedDurationHelp').innerText = 'Waiting for a road route.';
        updateMapsButtons(null);
        document.querySelectorAll('.route-order-badge').forEach(badge => {
            badge.classList.remove('active', 'next');
            badge.innerText = 'Not optimized';
        });
        updateRouteNumbersOnMap(optimizedStops);
        renderStopList(optimizedStops, {
            title: 'Awaiting a road route',
            subtitle: 'Delivery locations are shown below. A recommended sequence is not available yet.',
        });
    }

    async function optimizeDriverOrdersRoute(shouldFocus) {
        expireDriverLocation();
        if (!deliveryStops.length) {
            setRouteBadge('No stops', 'warning');
            setRouteMessage('There are no active assigned orders with delivery coordinates to optimize.', 'warning');
            return;
        }
        if (!latestCurrentPosition) {
            setRouteBadge('GPS needed', 'warning');
            setRouteMessage('Wait for live truck GPS or start the live location feed before calculating a route.', 'warning');
            return;
        }

        cancelRouteOptimization();
        const version = optimizationRequestVersion;
        const generation = locationGeneration;
        const controller = new AbortController();
        optimizationController = controller;
        clearOptimizedRoute();
        setOptimizationBusy(true);
        setRouteBadge('Finding a route...', 'warning');
        setRouteMessage('Comparing road routes and asking AI to recommend a delivery sequence using the available cargo information...', 'warning');
        let timedOut = false;
        const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, 90000);

        try {
            const response = await fetch(routeOptimizationUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify({origin: {
                    lat: Number(latestCurrentPosition.lat),
                    lng: Number(latestCurrentPosition.lng),
                    recorded_at: new Date(currentGpsRecordedAt).toISOString(),
                }}),
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => null);
            expireDriverLocation();
            if (version !== optimizationRequestVersion || generation !== locationGeneration || !latestCurrentPosition) return;
            if (!response.ok || !payload?.success) {
                const message = response.status === 419 || response.status === 401
                    ? 'Your session expired. Refresh the page and sign in again.'
                    : payload?.message || 'The route service is unavailable. Please try again.';
                throw new Error(message);
            }
            const result = payload.result;
            if (!result?.route?.polyline?.encodedPolyline || !Array.isArray(result.orderedStops) || !result.orderedStops.length) {
                throw new Error('No usable road route was returned. Please try again.');
            }
            applyOptimizedRouteResult(result, shouldFocus);
        } catch (error) {
            expireDriverLocation();
            if (version !== optimizationRequestVersion || generation !== locationGeneration || !latestCurrentPosition) return;
            setRouteBadge('Route unavailable', 'warning');
            const message = timedOut ? 'The route request took too long. Please try again.'
                : error.name === 'TypeError' ? 'Could not connect to the route service. Check your connection and try again.'
                : error.message || 'The route service is unavailable. Please try again.';
            setRouteMessage(escapeHtml(message), 'warning');
        } finally {
            clearTimeout(timeout);
            if (version === optimizationRequestVersion) {
                optimizationController = null;
                setOptimizationBusy(false);
            }
        }
    }

    function applyOptimizedRouteResult(result, shouldFocus) {
        optimizedStops = result.orderedStops;
        routeHasBeenOptimized = true;
        lastRouteResult = result;
        const recommendation = result.recommendation || {};
        const usedAi = recommendation.decision_source === 'openai';
        renderStopList(optimizedStops, {
            title: usedAi ? 'AI recommended sequence' : 'Automatic route recommendation',
            subtitle: result.strategy || 'Based on available road routes.',
        });
        document.getElementById('routeStopsCount').innerText = String(optimizedStops.length);
        updateTableRouteBadges(optimizedStops);
        updateMapsButtons(buildGoogleMapsMultiStopUrl(optimizedStops));
        updateRouteNumbersOnMap(optimizedStops);
        drawRoutesApiPolyline(result.route, optimizedStops, shouldFocus);
        document.getElementById('optimizedDistance').innerText = getRouteDistanceText(result.route);
        document.getElementById('optimizedDuration').innerText = getRouteDurationText(result.route);
        document.getElementById('optimizedDistanceHelp').innerText = 'Road distance from Google Maps.';
        document.getElementById('optimizedDurationHelp').innerText = 'Estimated drive time with current traffic.';
        const warnings = Array.isArray(result.warnings) ? result.warnings : [];
        const message = [
            usedAi ? 'AI recommended this road route.' : 'AI is unavailable. ColdTrace selected a road route using its calculated scores.',
            recommendation.reason,
            recommendation.driver_action,
            recommendation.cold_chain_warning,
            ...warnings,
            optimizedStops.length > 4
                ? 'Google Maps opens the first delivery for this longer route. Complete that delivery, then optimize the remaining orders.'
                : 'Open Google Maps to start navigation. Google Maps may adjust the road path.',
        ].filter(value => typeof value === 'string' && value.trim());
        const needsAttention = !usedAi || warnings.length > 0 || ['warning', 'critical'].includes(recommendation.risk_level);
        setRouteBadge(usedAi ? 'AI recommended' : 'Road route ready', needsAttention ? 'warning' : 'success');
        setRouteMessage([...new Set(message)].map(escapeHtml).join(' '), needsAttention ? 'warning' : 'success');
        window.ColdTraceSpeech?.setRecommendation(message, () =>
            routeHasBeenOptimized && !!latestCurrentPosition && ColdTraceLocation.fresh(currentGpsRecordedAt));
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
            const label = button.querySelector('[data-maps-label]');
            if (label) label.innerText = url && optimizedStops.length > 4 ? 'Navigate first stop' : 'Open Google Maps';

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

        // Mobile Google Maps URLs support three intermediate waypoints.
        // For larger plans, explicitly navigate the first stop instead of
        // sending a URL whose additional deliveries could be dropped.
        if (stops.length > 4) stops = stops.slice(0, 1);

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

        if (lastRouteResult?.route) {
            drawRoutesApiPolyline(lastRouteResult.route, lastRouteResult.orderedStops, false);
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
        expireDriverLocation();
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

    function handleDriverTelemetryPayload(data, topic, packet = {}) {
        if (!data || !expectedDeviceCode || data.device_code !== expectedDeviceCode || topic !== subscribeTopic) return;
        const timestamp = ColdTraceLocation.packetTime(data, packet);
        if (timestamp === null || timestamp < currentGpsRecordedAt) return;
        if (data.gps_valid !== true || !ColdTraceLocation.valid(data.latitude, data.longitude)) return;
        if (data.satellites !== undefined && (!ColdTraceLocation.numeric(data.satellites) || Number(data.satellites) < 4)) return;
        if (data.hdop !== undefined && (!ColdTraceLocation.numeric(data.hdop) || Number(data.hdop) < 0 || Number(data.hdop) > 5)) return;
        updateCurrentGpsDisplay({lat: Number(data.latitude), lng: Number(data.longitude)},
            'Live verified ESP32 GPS', timestamp, 'esp32');
        if (routeHasBeenOptimized) updateMapsButtons(buildGoogleMapsMultiStopUrl(optimizedStops));
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

    mqttClient.on('message', function (topic, message, packet) {
        try {
            const data = JSON.parse(message.toString());
            handleDriverTelemetryPayload(data, topic, packet);
        } catch (error) {
            console.error('Invalid MQTT telemetry payload:', error);
        }
    });

    window.addEventListener('pagehide', () => mqttClient.end());
    mqttClient.on('offline', () => {
        document.getElementById('currentGpsHelp').innerText = 'Live connection lost. Reconnecting; the last location expires automatically.';
    });

    mqttClient.on('error', function (error) {
        console.error('MQTT connection error:', error);
    });
</script>
@endif
@endpush
