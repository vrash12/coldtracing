@extends('layouts.app')

@section('title', 'Delivery ' . $order->order_code)

@section('content')

@php
    $latestTelemetry = $latestTelemetry ?? null;
    $monitoredProduct = $monitoredProduct ?? $order->orderItems->first()?->product;

    $hasDeliveryCoordinates = $order->delivery_lat && $order->delivery_lng;
    $deliveryLat = $hasDeliveryCoordinates ? (float) $order->delivery_lat : null;
    $deliveryLng = $hasDeliveryCoordinates ? (float) $order->delivery_lng : null;


    $hasCurrentGps = $hasCurrentGps ?? (
        $latestTelemetry &&
        $latestTelemetry->latitude !== null &&
        $latestTelemetry->longitude !== null
    );

    $currentLat = $hasCurrentGps
        ? (float) ($currentLat ?? $latestTelemetry?->latitude)
        : null;
    $currentLng = $hasCurrentGps
        ? (float) ($currentLng ?? $latestTelemetry?->longitude)
        : null;

    $temperatureValue = $latestTelemetry?->temperature !== null
        ? number_format((float) $latestTelemetry->temperature, 2)
        : null;

    $mktValue = $latestTelemetry?->mkt_value !== null
        ? number_format((float) $latestTelemetry->mkt_value, 2)
        : null;

    $rslHours = $latestTelemetry?->rsl_hours !== null
        ? (float) $latestTelemetry->rsl_hours
        : null;

    $initialShelfLifeHours = $monitoredProduct?->initial_shelf_life_hours !== null
        ? (float) $monitoredProduct->initial_shelf_life_hours
        : null;

    $rslPercentage = $rslHours !== null && $initialShelfLifeHours && $initialShelfLifeHours > 0
        ? max(0, min(100, ($rslHours / $initialShelfLifeHours) * 100))
        : null;

    $rslClass = match (true) {
        $rslPercentage === null => 'neutral',
        $rslPercentage <= 10 => 'critical',
        $rslPercentage <= 30 => 'warning',
        default => 'good',
    };

    $rslStatus = match ($rslClass) {
        'critical' => 'Critical shelf-life risk',
        'warning' => 'Shelf life is reduced',
        'good' => 'Shelf life is acceptable',
        default => 'Waiting for processed telemetry',
    };

    $temperatureClass = $temperatureClass ?? 'neutral';
    $temperatureStatus = $temperatureStatus ?? 'No Data';
    $isSimulatedTemperature = str_starts_with(
        (string) $latestTelemetry?->device?->device_code,
        'SIM-'
    );

    $currentGpsText = $hasCurrentGps
        ? number_format($currentLat, 7) . ', ' . number_format($currentLng, 7)
        : 'No GPS reading yet';

    $googleMapsApiKey = $googleMapsApiKey ?? config('services.google_maps.key', env('GOOGLE_MAPS_API_KEY'));


    $deliveryForMaps = $hasDeliveryCoordinates
        ? $deliveryLat . ',' . $deliveryLng
        : $order->delivery_address;

    $driverMapsUrl = $deliveryForMaps
        ? 'https://www.google.com/maps/dir/?api=1'
            . '&destination=' . urlencode($deliveryForMaps)
            . '&travelmode=driving'
            . '&avoid=' . urlencode('highways,tolls')
        : null;
    $receiverPhoneRaw = $order->receiver?->phone;
    $receiverPhoneLink = $receiverPhoneRaw
        ? preg_replace('/[^0-9+]/', '', $receiverPhoneRaw)
        : null;
@endphp

<div
    class="ct-index driver-order-show-page"
    id="driverOrderPage"
    data-telemetry-url="{{ route('driver.orders.telemetry.latest', $order) }}"
    data-poll-interval="5000"
>

    <section class="hero-panel">
        <div class="hero-left">
            <span class="eyebrow">Current delivery</span>

            <h1>{{ $order->order_code }}</h1>

            <p>{{ $order->delivery_address }}</p>

            <div class="hero-tags">
                <span class="status-badge status-{{ $order->status }}">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>

                <span class="soft-chip" id="telemetryConnectionChip">
                    Telemetry: Waiting
                </span>
            </div>
        </div>

        <div class="hero-actions" aria-label="Driver quick actions">
            <a href="{{ route('driver.orders.index') }}" class="secondary-button">
                <i class="bi bi-arrow-left"></i>
                Orders
            </a>

            @if ($receiverPhoneLink)
                <a href="tel:{{ $receiverPhoneLink }}" class="secondary-button">
                    <i class="bi bi-telephone"></i>
                    Call receiver
                </a>
            @endif

            @if ($driverMapsUrl)
                <a href="{{ $driverMapsUrl }}" target="_blank" rel="noopener" class="secondary-button">
                    <i class="bi bi-map"></i>
                    Open Maps
                </a>
            @endif

            <button type="button" class="primary-button" onclick="startInPageNavigation()">
                <i class="bi bi-signpost-split"></i>
                Start route
            </button>
        </div>
    </section>

    <section class="driver-quick-summary" aria-label="Important delivery summary">
        <div class="quick-card destination">
            <span>Destination</span>
            <strong>{{ $order->delivery_address }}</strong>
            <small>Tap Start Route to show in-page navigation.</small>
        </div>

        <div class="quick-card receiver">
            <span>Receiver</span>
            <strong>{{ $order->receiver?->name ?? 'N/A' }}</strong>
            <small>{{ $order->receiver?->phone ?? $order->receiver?->email ?? 'No contact available' }}</small>
        </div>

        <div class="quick-card schedule">
            <span>Expected Delivery</span>
            <strong>{{ $order->expected_delivery_at?->format('M d, Y') ?? 'Not set' }}</strong>
            <small>{{ $order->expected_delivery_at?->format('h:i A') ?? 'No preferred time' }}</small>
        </div>
    </section>

    <section class="live-panel">
        <div class="section-title-row">
            <div>
                <span class="section-kicker">Cargo condition</span>
                <h2>Live temperature and shelf life</h2>
                <p>These readings refresh automatically while the delivery is active.</p>
            </div>

            <div class="last-reading-card">
                <span>Last Reading</span>
                <strong id="liveLastReading">
                    {{ $latestTelemetry?->recorded_at?->format('M d, Y h:i A') ?? 'No telemetry yet' }}
                </strong>
            </div>
        </div>

        <div class="telemetry-grid">
            <article class="telemetry-card temperature-card {{ $temperatureClass }}" id="temperatureCard">
                <div class="telemetry-icon">
                    <i class="bi bi-thermometer-half"></i>
                </div>

                <div class="telemetry-copy">
                    <span>Current Temperature</span>
                    <strong id="liveTemperature" aria-live="polite">
                        {{ $temperatureValue !== null ? $temperatureValue . ' °C' : 'No Data' }}
                    </strong>
                    <small id="liveTemperatureStatus">
                        {{ $temperatureStatus }}{{ $isSimulatedTemperature ? ' · simulated API data' : '' }}
                    </small>
                </div>
            </article>

            <article class="telemetry-card gps-card" id="gpsCard">
                <div class="telemetry-icon">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>

                <div class="telemetry-copy">
                    <span>Current GPS</span>
                    <strong id="liveGps" aria-live="polite">{{ $currentGpsText }}</strong>
                    <small id="liveGpsStatus">
                        {{ $hasCurrentGps
                            ? (($gpsSource ?? 'esp32') === 'software' ? 'Live device/API fix' : 'Verified ESP32 fix')
                                . (isset($gpsAgeSeconds) ? ' · ' . $gpsAgeSeconds . 's ago' : '')
                            : 'Waiting for a recent live-location fix' }}
                    </small>
                </div>
            </article>

            <article class="telemetry-card mkt-card" id="mktCard">
                <div class="telemetry-icon">
                    <i class="bi bi-activity"></i>
                </div>

                <div class="telemetry-copy">
                    <span>Mean Kinetic Temperature</span>
                    <strong id="liveMkt" aria-live="polite">
                        {{ $mktValue !== null ? $mktValue . ' °C' : 'N/A' }}
                    </strong>
                    <small id="liveMktStatus">
                        {{ $mktValue !== null ? 'Calculated from saved trip temperature history' : 'Waiting for enough telemetry data' }}
                    </small>
                </div>
            </article>

            <article class="telemetry-card rsl-card {{ $rslClass }}" id="telemetryRslCard">
                <div class="telemetry-icon">
                    <i class="bi bi-hourglass-split"></i>
                </div>

                <div class="telemetry-copy">
                    <span>Remaining Shelf Life</span>
                    <strong id="liveRsl" aria-live="polite">
                        {{ $rslHours !== null ? number_format($rslHours, 2) . ' hrs' : 'N/A' }}
                    </strong>
                    <small id="liveRslStatus">{{ $rslStatus }}</small>
                </div>
            </article>
        </div>
    </section>

    <section class="eta-panel">
        <div class="section-title-row">
            <div>
                <span class="section-kicker">Route timing</span>
                <h2>Estimated arrival</h2>
                <p>Start the route to calculate travel time and distance.</p>
            </div>

            <span class="eta-badge" id="etaStatusBadge">
                Waiting for route
            </span>
        </div>

        <div class="eta-grid">
            <div class="eta-card">
                <span>Estimated Arrival Time</span>
                <strong id="etaArrivalTime">N/A</strong>
                <small id="etaArrivalNote">Start navigation to calculate ETA.</small>
            </div>

            <div class="eta-card">
                <span>Travel Time</span>
                <strong id="etaTravelTime">N/A</strong>
                <small>Based on traffic-aware route duration</small>
            </div>

            <div class="eta-card">
                <span>Distance Remaining</span>
                <strong id="etaDistance">N/A</strong>
                <small>Current vehicle GPS to delivery location</small>
            </div>

            <div class="eta-card">
                <span>Traffic-Aware ETA</span>
                <strong id="etaTraffic">N/A</strong>
                <small id="etaTrafficNote">Routes API traffic-aware routing will appear here.</small>
            </div>
        </div>
    </section>

    <section class="ai-route-panel is-collapsed" id="aiRoutePanel">
        <div class="section-title-row">
            <div>
                <span class="section-kicker">Advanced route analysis</span>
                <h2>Route recommendation</h2>
                <p>Compare route scores and request an AI explanation when needed.</p>
            </div>

            <div class="ai-header-actions">
                <button type="button" class="secondary-button advanced-toggle" onclick="toggleRouteAnalysis(this)" aria-expanded="false" aria-controls="aiRouteAnalysisContent">
                    <i class="bi bi-chevron-down"></i>
                    <span>Show analysis</span>
                </button>

                <span class="ai-badge" id="aiRecommendationBadge">
                    Waiting for route
                </span>

                <button type="button" class="secondary-button route-score-button" onclick="openRouteScoreModal()">
                    <i class="bi bi-calculator"></i>
                    Show Route Scores
                </button>

                @if (!empty($googleMapsApiKey))
                    <button type="button" class="secondary-button route-score-button" onclick="requestOpenAiRouteRecommendation(latestScoredRoutes)">
                        <i class="bi bi-stars"></i>
                        Ask AI
                    </button>
                @endif
            </div>
        </div>

        <x-route-speech />

        <div class="ai-grid" id="aiRouteAnalysisContent">
            <div class="ai-card">
                <span>Recommended Action</span>
                <strong id="aiRecommendedAction">Start navigation to calculate route.</strong>
                <small id="aiReason">ColdTrace needs current GPS and delivery destination.</small>
            </div>

            <div class="ai-card">
                <span>Best Route Distance</span>
                <strong id="aiDistance">N/A</strong>
                <small>Calculated inside this page</small>
            </div>

            <div class="ai-card">
                <span>Route ETA</span>
                <strong id="aiDuration">N/A</strong>
                <small id="aiEtaTime">Estimated arrival will appear here</small>
            </div>

            <div class="ai-card risk-card">
                <span>Spoilage Risk</span>
                <strong id="aiRisk">N/A</strong>
                <small id="aiRiskReason">Waiting for temperature and route data</small>
            </div>

            <div class="ai-card ai-rsl-card {{ $rslClass }}" id="aiRslCard">
                <span>Remaining Shelf Life</span>
                <strong id="remainingShelfLife">
                    {{ $rslHours !== null ? number_format($rslHours, 2) . ' hrs' : 'N/A' }}
                </strong>
                <small id="remainingShelfLifeReason">
                    {{ $rslHours !== null ? 'Calculated and stored by the ColdTrace backend.' : 'Waiting for processed telemetry data.' }}
                </small>
            </div>
        </div>
    </section>

    <div class="route-score-modal" id="routeScoreModal" aria-hidden="true">
        <div class="route-score-backdrop" onclick="closeRouteScoreModal()"></div>

        <div class="route-score-dialog" role="dialog" aria-modal="true" aria-labelledby="routeScoreTitle">
            <div class="route-score-header">
                <div>
                    <span class="section-kicker">Weighted Rule</span>
                    <h2 id="routeScoreTitle">ColdTrace Route Score Formula</h2>
                    <p>
                        Each route is ranked using normalized ETA, normalized distance, temperature risk,
                        and remaining shelf life risk.
                    </p>
                </div>

                <button type="button" class="route-score-close" onclick="closeRouteScoreModal()" aria-label="Close route score modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="formula-box">
                <strong>Formula</strong>
                <p>
                    Route Score = (0.35 × ETA<sub>norm</sub>) +
                    (0.20 × Distance<sub>norm</sub>) +
                    (0.25 × Temperature Risk) +
                    (0.20 × RSL Risk)
                </p>

                <small>
                    ETA<sub>norm</sub> = route ETA ÷ maximum ETA, and Distance<sub>norm</sub> = route distance ÷ maximum distance.
                    The route with the lowest score is recommended by ColdTrace.
                </small>
            </div>

            <div class="route-score-table-wrap">
                <table class="route-score-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Route</th>
                            <th>ETA</th>
                            <th>Distance</th>
                            <th>ETA Norm</th>
                            <th>Distance Norm</th>
                            <th>Temp Risk</th>
                            <th>RSL Risk</th>
                            <th>Final Score</th>
                        </tr>
                    </thead>

                    <tbody id="routeScoreTableBody">
                        <tr>
                            <td colspan="9">Start navigation to calculate route scores.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <section class="map-panel">
        <div class="section-title-row">
            <div>
                <span class="section-kicker">Navigation</span>
                <h2>Route to destination</h2>
                <p>Start the route to see directions from the latest vehicle position.</p>
            </div>

            <div class="map-legend">
          
                <span><i class="legend-dot current"></i> Current Vehicle</span>
                <span><i class="legend-dot delivery"></i> Delivery</span>
            </div>
        </div>

        @if (empty($googleMapsApiKey))
            <div class="warning-box">
                Google Maps API key is missing. Add <strong>GOOGLE_MAPS_API_KEY</strong> to your <strong>.env</strong> file.
                Telemetry cards can still update, but the map, ETA, and route will not load.
            </div>
        @endif

        <div class="location-grid">
        

            <div class="location-card current-card">
                <span>Current Vehicle GPS</span>
                <strong id="liveLocationName">
                    {{ $hasCurrentGps ? 'Vehicle / ESP32 Location' : 'No GPS yet' }}
                </strong>
                <small id="liveLocationCoords">{{ $currentGpsText }}</small>
            </div>

            <div class="location-card delivery-card">
                <div class="location-card-top">
                    <span>Delivery</span>

                    <button type="button" class="copy-location-button" onclick="copyDeliveryAddress()">
                        <i class="bi bi-clipboard"></i>
                        Copy
                    </button>
                </div>

                <strong id="deliveryAddressText">{{ $order->delivery_address }}</strong>

                <small>
                    {{ $order->delivery_lat ?? 'No latitude' }},
                    {{ $order->delivery_lng ?? 'No longitude' }}
                </small>
            </div>
        </div>

        <div class="navigation-layout">
            <div id="driverOrderMap"></div>

            <aside class="route-panel">
                <div class="route-panel-header">
                    <span>Route Guidance</span>
                    <strong id="routeSummaryTitle">No active route yet</strong>
                    <small id="routeSummaryText">Click Start In-Page Navigation.</small>
                </div>

                <div class="alternate-route-section">
                    <div class="alternate-route-section-title">
                        <span>Algorithm Route Options</span>
                        <small>Top 3 routes ranked by ColdTrace score: ETA + distance + temperature/RSL risk.</small>
                    </div>

                    <div class="alternate-route-list" id="alternateRouteList">
                        <div class="empty-route">Alternative routes will appear after ColdTrace calculates navigation.</div>
                    </div>
                </div>

                <div class="route-steps" id="routeSteps">
                    <div class="empty-route">
                        Route steps and ETA will appear here after the route is calculated.
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <div class="content-grid driver-supporting-details">
        @if ($order->notes)
            <section class="details-panel">
                <div class="section-title-row compact">
                    <div>
                        <span class="section-kicker">Instructions</span>
                        <h2>Delivery notes</h2>
                    </div>
                </div>
                <p class="driver-delivery-note">{{ $order->notes }}</p>
            </section>
        @endif

        <section class="products-panel">
            <div class="section-title-row compact">
                <div>
                    <span class="section-kicker">Cargo</span>
                    <h2>Items to deliver</h2>
                </div>
            </div>

            <div class="product-list">
                @forelse ($order->orderItems as $item)
                    <div class="product-row">
                        <div>
                            <strong>{{ $item->product?->name ?? 'Product unavailable' }}</strong>
                            @if ($item->product)
                                <small>Safe range {{ $item->product->min_temp }}°C–{{ $item->product->max_temp }}°C</small>
                            @endif
                        </div>
                        <em>{{ $item->quantity }} {{ $item->unit }}</em>
                    </div>
                @empty
                    <div class="empty-box">No products listed.</div>
                @endforelse
            </div>
        </section>
    </div>


    <nav class="mobile-driver-action-bar" aria-label="Mobile driver actions">
        @if ($driverMapsUrl)
            <a href="{{ $driverMapsUrl }}" target="_blank" rel="noopener">
                <i class="bi bi-map"></i>
                Maps
            </a>
        @endif

        <button type="button" onclick="startInPageNavigation()">
            <i class="bi bi-signpost-split"></i>
            Start Route
        </button>

        @if ($receiverPhoneLink)
            <a href="tel:{{ $receiverPhoneLink }}">
                <i class="bi bi-telephone"></i>
                Call
            </a>
        @endif
    </nav>

</div>

@endsection


@push('scripts')
<script>
    const coldTracePage = document.getElementById('driverOrderPage');
    const initialShelfLifeHours = Number(@json($initialShelfLifeHours));
    const safeMinTemp = @json($monitoredProduct?->min_temp !== null ? (float) $monitoredProduct->min_temp : null);
    const safeMaxTemp = @json($monitoredProduct?->max_temp !== null ? (float) $monitoredProduct->max_temp : null);

    let latestRslResult = null;
    let detailGpsRecordedAt = ColdTraceLocation.time(@json($gpsRecordedAt ?? null));
    let detailReadingRecordedAt = ColdTraceLocation.time(@json($latestTelemetry?->recorded_at?->toIso8601String()));

    function clearDetailLocation() {
        setText('liveGps', 'No live GPS');
        setText('liveGpsStatus', 'Truck disconnected or GPS unavailable');
        setText('liveLocationName', 'Waiting for live GPS');
        setText('liveLocationCoords', 'Last location expired');
        if (typeof clearCurrentGpsMarker === 'function') clearCurrentGpsMarker();
    }
    function expireDetailLocation() {
        if (!ColdTraceLocation.fresh(detailGpsRecordedAt)) clearDetailLocation();
        if (!ColdTraceLocation.fresh(detailReadingRecordedAt)) setTelemetryChip('Telemetry: No recent readings');
    }
    const detailLocationTimer = setInterval(expireDetailLocation, 1000);
    document.addEventListener('visibilitychange', expireDetailLocation);
    window.addEventListener('pagehide', () => clearInterval(detailLocationTimer));

    function setText(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent = value;
        }
    }

    function toFiniteNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const number = Number(value);
        return Number.isFinite(number) ? number : null;
    }

    function hoursToReadableTime(hours) {
        const value = toFiniteNumber(hours);

        if (value === null) {
            return 'N/A';
        }

        if (value <= 0) {
            return 'Expired';
        }

        const totalMinutes = Math.max(0, Math.round(value * 60));
        const days = Math.floor(totalMinutes / 1440);
        const hoursPart = Math.floor((totalMinutes % 1440) / 60);
        const minutesPart = totalMinutes % 60;

        if (days > 0) {
            return `${days}d ${hoursPart}h ${minutesPart}m`;
        }

        if (hoursPart > 0) {
            return `${hoursPart}h ${minutesPart}m`;
        }

        return `${minutesPart}m`;
    }

    function deriveRslState(remainingHours) {
        const value = toFiniteNumber(remainingHours);
        const shelfLife = Number.isFinite(initialShelfLifeHours) && initialShelfLifeHours > 0
            ? initialShelfLifeHours
            : null;

        if (value === null) {
            return {
                remainingHours: null,
                riskLevel: 'Unknown',
                riskClass: 'neutral',
                scorePenalty: 300,
                scoreValue: 0.50,
                message: 'Waiting for backend-calculated remaining shelf life.'
            };
        }

        const percentage = shelfLife !== null
            ? Math.max(0, Math.min(100, (value / shelfLife) * 100))
            : null;

        if (value <= 0 || (percentage !== null && percentage <= 10)) {
            return {
                remainingHours: value,
                riskLevel: 'Critical',
                riskClass: 'critical',
                scorePenalty: 3000,
                scoreValue: 1.00,
                message: 'Remaining shelf life is depleted or critically low.'
            };
        }

        if (percentage !== null && percentage <= 30) {
            return {
                remainingHours: value,
                riskLevel: 'High',
                riskClass: 'warning',
                scorePenalty: 1200,
                scoreValue: 1.00,
                message: 'Remaining shelf life is low. Prioritize this delivery.'
            };
        }

        return {
            remainingHours: value,
            riskLevel: 'Low',
            riskClass: 'good',
            scorePenalty: 0,
            scoreValue: 0.00,
            message: 'Remaining shelf life is acceptable.'
        };
    }

    function updateRslPanels(remainingHours) {
        latestRslResult = deriveRslState(remainingHours);

        const display = latestRslResult.remainingHours === null
            ? 'N/A'
            : `${latestRslResult.remainingHours.toFixed(2)} hrs`;

        const readable = hoursToReadableTime(latestRslResult.remainingHours);

        setText('liveRsl', display);
        setText('liveRslStatus', latestRslResult.message);
        setText('remainingShelfLife', readable);
        setText(
            'remainingShelfLifeReason',
            latestRslResult.remainingHours === null
                ? 'Waiting for processed telemetry data.'
                : `Backend-calculated RSL: ${display}.`
        );

        ['telemetryRslCard', 'aiRslCard'].forEach(function (id) {
            const card = document.getElementById(id);

            if (!card) {
                return;
            }

            card.classList.remove('good', 'warning', 'critical', 'neutral');
            card.classList.add(latestRslResult.riskClass);
        });
    }

    function getLatestRslRiskLevel() {
        if (!latestRslResult) {
            return null;
        }

        return {
            label: latestRslResult.riskLevel,
            className: latestRslResult.riskClass === 'neutral' ? 'warning' : latestRslResult.riskClass,
            reason: `${latestRslResult.message} Remaining shelf life: ${hoursToReadableTime(latestRslResult.remainingHours)}.`,
            scorePenalty: latestRslResult.scorePenalty,
            scoreValue: latestRslResult.scoreValue
        };
    }

    function chooseHigherRisk(primaryRisk, secondaryRisk) {
        if (!secondaryRisk) {
            return primaryRisk;
        }

        if (!primaryRisk) {
            return secondaryRisk;
        }

        return Number(secondaryRisk.scorePenalty || 0) > Number(primaryRisk.scorePenalty || 0)
            ? secondaryRisk
            : primaryRisk;
    }

    function setTelemetryChip(text, state = null) {
        const chip = document.getElementById('telemetryConnectionChip');

        if (!chip) {
            return;
        }

        chip.textContent = text;
        chip.classList.remove('connected', 'error');

        if (state) {
            chip.classList.add(state);
        }
    }

    function updateTemperatureCard(temperature, status = null, className = null) {
        const numericTemperature = toFiniteNumber(temperature);
        const card = document.getElementById('temperatureCard');
        const quickCard = document.querySelector('.quick-card.temperature');

        let resolvedClass = className;
        let resolvedStatus = status;

        if (!resolvedClass || !resolvedStatus) {
            if (numericTemperature === null) {
                resolvedClass = 'neutral';
                resolvedStatus = 'No Data';
            } else if (safeMinTemp !== null && numericTemperature < Number(safeMinTemp)) {
                resolvedClass = 'warning';
                resolvedStatus = 'Too Low';
            } else if (safeMaxTemp !== null && numericTemperature > Number(safeMaxTemp)) {
                resolvedClass = 'critical';
                resolvedStatus = 'Too High';
            } else {
                resolvedClass = 'safe';
                resolvedStatus = 'Safe';
            }
        }

        [card, quickCard].forEach(function (element) {
            if (!element) {
                return;
            }

            element.classList.remove('safe', 'warning', 'critical', 'neutral');
            element.classList.add(resolvedClass);
        });

        setText('liveTemperatureStatus', resolvedStatus);
        setText('quickTemperatureStatus', resolvedStatus);
    }

    async function copyDeliveryAddress() {
        const address = document.getElementById('deliveryAddressText')?.textContent?.trim()
            || @json($order->delivery_address);

        try {
            await navigator.clipboard.writeText(address);

            const button = document.querySelector('.copy-location-button');

            if (button) {
                const oldText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Copied';

                window.setTimeout(function () {
                    button.innerHTML = oldText;
                }, 1800);
            }
        } catch (error) {
            console.error('Unable to copy delivery address:', error);
        }
    }

    function toggleRouteAnalysis(button) {
        const panel = document.getElementById('aiRoutePanel');

        if (!panel) {
            return;
        }

        const willOpen = panel.classList.contains('is-collapsed');
        panel.classList.toggle('is-collapsed', !willOpen);
        button?.setAttribute('aria-expanded', String(willOpen));

        const label = button?.querySelector('span');
        const icon = button?.querySelector('i');

        if (label) {
            label.textContent = willOpen ? 'Hide analysis' : 'Show analysis';
        }

        if (icon) {
            icon.className = willOpen ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        }
    }

    window.toggleRouteAnalysis = toggleRouteAnalysis;

    updateRslPanels(@json($rslHours));
</script>

@if (!empty($googleMapsApiKey))
<script>
    let coldTraceMap = null;
    let coldTraceBounds = null;
    let currentTruckMarker = null;
    let deliveryMarker = null;
    let coldTraceRoutePolyline = null;
    let alternateRoutePolylines = [];
    let latestScoredRoutes = [];
    let selectedRouteOriginalIndex = null;
    let recommendedRouteOriginalIndex = null;
    let AdvancedMarkerElementClass = null;
    let latestCurrentPosition = null;
    let routeRequestVersion = 0;
    let aiRecommendationRequestVersion = 0;

    function clearCurrentGpsMarker() {
        if (!latestCurrentPosition && !currentTruckMarker) return;
        latestCurrentPosition = null;
        currentTruckMarker && (currentTruckMarker.map = null);
        currentTruckMarker = null;
        routeRequestVersion++;
        clearRoutePolylines();
        latestScoredRoutes = [];
        selectedRouteOriginalIndex = null;
        recommendedRouteOriginalIndex = null;
        updateRoutePanelEmpty('Waiting for live GPS', 'Routing resumes when a fresh location arrives.');
        updateEtaPanel(null);
        updateAiPanel(null, null, null);
        renderAlternateRoutes([]);
    }

    const googleApiKey = @json($googleMapsApiKey);
    const csrfToken = @json(csrf_token());

    const aiRouteRecommendationUrl = @json(
        route('driver.orders.aiRouteRecommendation', $order)
    );

    const currentOrderForAi = {
        order_id: @json($order->id),
        order_code: @json($order->order_code),
        delivery_address: @json($order->delivery_address),
        expected_delivery_at: @json($order->expected_delivery_at?->toDateTimeString()),
        product: {
            name: @json($monitoredProduct?->name),
            min_temp: @json($monitoredProduct?->min_temp),
            max_temp: @json($monitoredProduct?->max_temp),
            initial_shelf_life_hours: @json($monitoredProduct?->initial_shelf_life_hours),
        }
    };

    const initialDeliveryPosition = @json($hasDeliveryCoordinates ? ['lat' => $deliveryLat, 'lng' => $deliveryLng] : null);
    const initialCurrentPosition = @json($hasCurrentGps ? ['lat' => $currentLat, 'lng' => $currentLng] : null);

    async function initDriverOrderMap() {
        const mapElement = document.getElementById('driverOrderMap');

        if (!mapElement || !window.google || !google.maps) {
            return;
        }

        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([
            google.maps.importLibrary('maps'),
            google.maps.importLibrary('marker'),
        ]);

        AdvancedMarkerElementClass = AdvancedMarkerElement;
        if (!latestCurrentPosition && ColdTraceLocation.fresh(detailGpsRecordedAt)) latestCurrentPosition = initialCurrentPosition;
        if (!ColdTraceLocation.fresh(detailGpsRecordedAt)) latestCurrentPosition = null;

        const fallbackPosition = latestCurrentPosition || initialDeliveryPosition || {
            lat: 14.5995,
            lng: 120.9842,
        };

        coldTraceMap = new Map(mapElement, {
            center: fallbackPosition,
            zoom: 14,
            gestureHandling: 'greedy',
            scrollwheel: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            mapId: 'DEMO_MAP_ID',
        });

        coldTraceBounds = new google.maps.LatLngBounds();


        if (initialDeliveryPosition) {
            deliveryMarker = createMapMarker(initialDeliveryPosition, 'delivery', 'Delivery Location');
            coldTraceBounds.extend(initialDeliveryPosition);
        }

        if (latestCurrentPosition) {
            currentTruckMarker = createMapMarker(latestCurrentPosition, 'current', 'Current Vehicle / ESP32 Location');
            coldTraceBounds.extend(latestCurrentPosition);
        }

        if (latestCurrentPosition || initialDeliveryPosition) {
            coldTraceMap.fitBounds(coldTraceBounds);
        } else {
            coldTraceMap.setZoom(11);
        }

        calculateInternalRoute(false);
    }

    function createMapMarker(position, type, title) {
        return new AdvancedMarkerElementClass({
            map: coldTraceMap,
            position: position,
            title: title,
            content: createMarkerContent(type),
        });
    }


function createMarkerContent(type) {
    if (type === 'current') {
        return window.createColdTraceTruckMarker(@json($order->trip?->truck_id ?? auth()->user()->assignedTruck?->id));
    }
    const marker = document.createElement('div');

    marker.style.width = '46px';
    marker.style.height = '46px';
    marker.style.borderRadius = '999px';
    marker.style.display = 'flex';
    marker.style.alignItems = 'center';
    marker.style.justifyContent = 'center';
    marker.style.color = '#ffffff';
    marker.style.fontWeight = '950';
    marker.style.fontSize = '22px';
    marker.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.26)';
    marker.style.border = '3px solid #ffffff';

    marker.innerHTML = '📍';
    marker.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';

    return marker;
}

    function updateCurrentGpsMarker(lat, lng) {
        if (!ColdTraceLocation.fresh(detailGpsRecordedAt) || !ColdTraceLocation.valid(lat, lng)) return;
        const moved = !latestCurrentPosition || latestCurrentPosition.lat !== Number(lat) || latestCurrentPosition.lng !== Number(lng);
        const position = {
            lat: Number(lat),
            lng: Number(lng),
        };

        latestCurrentPosition = position;

        if (!coldTraceMap || !AdvancedMarkerElementClass) {
            return;
        }

        if (!currentTruckMarker) {
            currentTruckMarker = createMapMarker(position, 'current', 'Current Vehicle / ESP32 Location');
        } else {
            currentTruckMarker.position = position;
        }

        if (moved) calculateInternalRoute(false);
    }

    function startInPageNavigation() {
        const mapPanel = document.querySelector('.map-panel');

        if (mapPanel) {
            mapPanel.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        calculateInternalRoute(true);
    }

    async function calculateInternalRoute(shouldFocus) {
        window.ColdTraceSpeech?.clear();
        const requestVersion = ++routeRequestVersion;
        latestScoredRoutes = [];
        selectedRouteOriginalIndex = null;
        recommendedRouteOriginalIndex = null;
        if (!ColdTraceLocation.fresh(detailGpsRecordedAt)) clearCurrentGpsMarker();
        const origin = latestCurrentPosition;
        const destination = initialDeliveryPosition;

        if (!origin || !destination) {
            updateRoutePanelEmpty('Route unavailable', 'Current GPS or delivery location is missing.');
            updateEtaPanel(null);
            updateAiPanel(null, null, null);
            renderAlternateRoutes([]);
            return;
        }

        try {
            const result = await fetchCandidateRoutes(origin, destination);
            if (requestVersion !== routeRequestVersion || !ColdTraceLocation.fresh(detailGpsRecordedAt)) return;

            if (!result.routes || result.routes.length === 0) {
                console.error('Routes API error:', result.error || result.raw);
                updateRoutePanelEmpty('Route unavailable', 'ColdTrace could not calculate a route using Routes API.');
                updateEtaPanel(null);
                updateAiPanel(null, null, null);
                renderAlternateRoutes([]);
                return;
            }

            const scoredRoutes = scoreRoutes(result.routes).slice(0, 3);

            if (!scoredRoutes.length) {
                updateRoutePanelEmpty('Route unavailable', 'ColdTrace could not score the returned route options.');
                updateEtaPanel(null);
                updateAiPanel(null, null, null);
                renderAlternateRoutes([]);
                return;
            }

            latestScoredRoutes = scoredRoutes;
            selectedRouteOriginalIndex = scoredRoutes[0].originalIndex;
            recommendedRouteOriginalIndex = selectedRouteOriginalIndex;

            const selectedRoute = scoredRoutes[0].route;

            drawAllRoutePolylines(scoredRoutes, selectedRouteOriginalIndex);
            renderAlternateRoutes(scoredRoutes, selectedRouteOriginalIndex);
            renderRouteSteps(selectedRoute, scoredRoutes[0], scoredRoutes.length);
            updateEtaPanel(selectedRoute);
            updateAiPanel(selectedRoute, 0, scoredRoutes.length, scoredRoutes[0]);

            if (shouldFocus) {
                coldTraceMap.panTo(origin);
                coldTraceMap.setZoom(15);
                requestOpenAiRouteRecommendation(scoredRoutes);
            }

        } catch (error) {
            if (requestVersion !== routeRequestVersion) return;
            console.error('Routes API request failed:', error);
            updateRoutePanelEmpty('Route error', 'ColdTrace could not connect to the Routes API.');
            updateEtaPanel(null);
            updateAiPanel(null, null, null);
            renderAlternateRoutes([]);
        }
    }

    function buildRouteRequestBody(origin, destination, options = {}) {
        const body = {
            origin: {
                location: {
                    latLng: {
                        latitude: origin.lat,
                        longitude: origin.lng
                    }
                }
            },
            destination: {
                location: {
                    latLng: {
                        latitude: destination.lat,
                        longitude: destination.lng
                    }
                }
            },
            travelMode: 'DRIVE',
            routingPreference: options.routingPreference || 'TRAFFIC_AWARE',
            computeAlternativeRoutes: options.computeAlternativeRoutes ?? true,
            units: 'METRIC',
            languageCode: 'en-US',
            routeModifiers: {
                avoidHighways: true,
                avoidTolls: true
            }
        };

        if (options.trafficModel) {
            body.trafficModel = options.trafficModel;
        }

        return body;
    }

    async function fetchRoutesApi(body) {
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
                    'routes.legs.localizedValues',
                    'routes.legs.steps.localizedValues',
                    'routes.legs.steps.navigationInstruction'
                ].join(',')
            },
            body: JSON.stringify(body)
        });

        const result = await response.json();

        if (!response.ok || !result.routes || result.routes.length === 0) {
            return {
                routes: [],
                raw: result,
                error: result.error || null
            };
        }

        return {
            routes: result.routes,
            raw: result,
            error: null
        };
    }

    async function fetchCandidateRoutes(origin, destination) {
        const routeRequests = [
            {
                routingPreference: 'TRAFFIC_AWARE',
                computeAlternativeRoutes: true
            },
            {
                routingPreference: 'TRAFFIC_AWARE_OPTIMAL',
                trafficModel: 'BEST_GUESS',
                computeAlternativeRoutes: true
            },
            {
                routingPreference: 'TRAFFIC_UNAWARE',
                computeAlternativeRoutes: true
            }
        ];

        const collectedRoutes = [];
        const seenPolylines = new Set();
        let lastRawResult = null;
        let lastError = null;

        for (const requestOptions of routeRequests) {
            const body = buildRouteRequestBody(origin, destination, requestOptions);
            const result = await fetchRoutesApi(body);

            lastRawResult = result.raw;
            lastError = result.error;

            result.routes.forEach(function (route) {
                const polyline = route.polyline?.encodedPolyline || '';
                const key = polyline || [route.distanceMeters, route.duration, route.staticDuration].join('|');

                if (!key || seenPolylines.has(key)) {
                    return;
                }

                seenPolylines.add(key);
                collectedRoutes.push(route);
            });

            if (collectedRoutes.length >= 3) {
                break;
            }
        }

        return {
            routes: collectedRoutes,
            raw: lastRawResult,
            error: lastError
        };
    }

    function clearRoutePolylines() {
        if (coldTraceRoutePolyline) {
            coldTraceRoutePolyline.setMap(null);
            coldTraceRoutePolyline = null;
        }

        alternateRoutePolylines.forEach(function (polyline) {
            polyline.setMap(null);
        });

        alternateRoutePolylines = [];
    }

    function drawAllRoutePolylines(scoredRoutes, selectedOriginalIndex) {
        if (!coldTraceMap || !Array.isArray(scoredRoutes) || !scoredRoutes.length) {
            return;
        }

        clearRoutePolylines();

        const bounds = new google.maps.LatLngBounds();
        let hasBounds = false;

        scoredRoutes.forEach(function (scoredRoute) {
            const route = scoredRoute.route;

            if (!route?.polyline?.encodedPolyline) {
                return;
            }

            const decodedPath = decodePolyline(route.polyline.encodedPolyline);
            const isSelected = Number(scoredRoute.originalIndex) === Number(selectedOriginalIndex);

            const polyline = new google.maps.Polyline({
                path: decodedPath,
                geodesic: true,
                strokeColor: isSelected ? '#2563eb' : '#94a3b8',
                strokeOpacity: isSelected ? 1 : 0.55,
                strokeWeight: isSelected ? 7 : 4,
                zIndex: isSelected ? 10 : 3,
                map: coldTraceMap,
            });

            polyline.addListener('click', function () {
                selectRouteOption(scoredRoute.originalIndex);
            });

            if (isSelected) {
                coldTraceRoutePolyline = polyline;
            } else {
                alternateRoutePolylines.push(polyline);
            }

            decodedPath.forEach(function (point) {
                bounds.extend(point);
                hasBounds = true;
            });
        });

        if (currentTruckMarker?.position) {
            bounds.extend(currentTruckMarker.position);
            hasBounds = true;
        }

        if (deliveryMarker?.position) {
            bounds.extend(deliveryMarker.position);
            hasBounds = true;
        }

        if (hasBounds) {
            coldTraceMap.fitBounds(bounds);
        }
    }

    function drawRoutesApiPolyline(route) {
        if (!route) {
            clearRoutePolylines();
            return;
        }

        drawAllRoutePolylines([
            {
                route: route,
                originalIndex: 0,
                score: getRouteColdTraceScore(route).score,
                risk: getRouteColdTraceScore(route).risk
            }
        ], 0);
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

    const ROUTE_WEIGHT_ETA = 0.35;
    const ROUTE_WEIGHT_DISTANCE = 0.20;
    const ROUTE_WEIGHT_TEMPERATURE = 0.25;
    const ROUTE_WEIGHT_RSL = 0.20;

    function getRiskScoreValue(risk) {
        if (!risk) {
            return 0.50;
        }

        if (Number.isFinite(Number(risk.scoreValue))) {
            return Number(risk.scoreValue);
        }

        const penalty = Number(risk.scorePenalty || 0);

        if (penalty >= 1200) {
            return 1.00;
        }

        if (penalty >= 500) {
            return 0.50;
        }

        return 0.00;
    }

    function getRouteColdTraceScore(route, maxDurationSeconds = null, maxDistanceMeters = null) {
        const distanceMeters = Number(route?.distanceMeters ?? 999999999);
        const durationSeconds = Number(getRouteDurationSeconds(route) ?? 999999999);

        const safeMaxDuration = Number(maxDurationSeconds) > 0 ? Number(maxDurationSeconds) : durationSeconds;
        const safeMaxDistance = Number(maxDistanceMeters) > 0 ? Number(maxDistanceMeters) : distanceMeters;

        const etaNorm = safeMaxDuration > 0 ? durationSeconds / safeMaxDuration : 1;
        const distanceNorm = safeMaxDistance > 0 ? distanceMeters / safeMaxDistance : 1;

        const temperature = getCurrentTemperatureNumber();
        const tempRisk = getTemperatureRiskLevel(temperature);

        const rslRisk = typeof getLatestRslRiskLevel === 'function'
            ? getLatestRslRiskLevel()
            : null;

        const tempRiskScore = getRiskScoreValue(tempRisk);
        const rslRiskScore = getRiskScoreValue(rslRisk);

        const finalRisk = typeof chooseHigherRisk === 'function'
            ? chooseHigherRisk(tempRisk, rslRisk)
            : tempRisk;

        const score =
            (ROUTE_WEIGHT_ETA * etaNorm) +
            (ROUTE_WEIGHT_DISTANCE * distanceNorm) +
            (ROUTE_WEIGHT_TEMPERATURE * tempRiskScore) +
            (ROUTE_WEIGHT_RSL * rslRiskScore);

        return {
            score: score,
            distanceMeters: distanceMeters,
            durationSeconds: durationSeconds,
            etaNorm: etaNorm,
            distanceNorm: distanceNorm,
            tempRiskScore: tempRiskScore,
            rslRiskScore: rslRiskScore,
            risk: finalRisk,
            tempRisk: tempRisk,
            rslRisk: rslRisk
        };
    }

    function scoreRoutes(routes) {
        const usableRoutes = Array.isArray(routes) ? routes : [];

        const maxDurationSeconds = Math.max(
            ...usableRoutes.map(function (route) {
                return Number(getRouteDurationSeconds(route) || 0);
            }),
            1
        );

        const maxDistanceMeters = Math.max(
            ...usableRoutes.map(function (route) {
                return Number(route?.distanceMeters || 0);
            }),
            1
        );

        return usableRoutes
            .map(function (route, index) {
                const scoreData = getRouteColdTraceScore(route, maxDurationSeconds, maxDistanceMeters);

                return {
                    route: route,
                    originalIndex: index,
                    score: scoreData.score,
                    distanceMeters: scoreData.distanceMeters,
                    durationSeconds: scoreData.durationSeconds,
                    etaNorm: scoreData.etaNorm,
                    distanceNorm: scoreData.distanceNorm,
                    tempRiskScore: scoreData.tempRiskScore,
                    rslRiskScore: scoreData.rslRiskScore,
                    risk: scoreData.risk,
                    tempRisk: scoreData.tempRisk,
                    rslRisk: scoreData.rslRisk
                };
            })
            .filter(function (item) {
                return item.route && Number.isFinite(item.score);
            })
            .sort(function (a, b) {
                return a.score - b.score;
            });
    }


    function roundForAi(value, decimals = 3) {
        const number = Number(value);

        if (!Number.isFinite(number)) {
            return null;
        }

        return Number(number.toFixed(decimals));
    }

    function getCurrentTemperatureStatusText() {
        const status = document.getElementById('liveTemperatureStatus');

        return status ? status.innerText : 'No Data';
    }

    function buildAiRoutePayload(scoredRoutes) {
        const routes = Array.isArray(scoredRoutes) ? scoredRoutes : [];

        return {
            order_id: currentOrderForAi.order_id,

            order: {
                order_code: currentOrderForAi.order_code,
                delivery_address: currentOrderForAi.delivery_address,
                expected_delivery_at: currentOrderForAi.expected_delivery_at,
            },

            product: currentOrderForAi.product,

            current_condition: {
                temperature: typeof getCurrentTemperatureNumber === 'function'
                    ? getCurrentTemperatureNumber()
                    : null,

                temperature_status: getCurrentTemperatureStatusText(),

                rsl_hours: typeof latestRslResult !== 'undefined'
                    ? roundForAi(latestRslResult?.remainingHours, 2)
                    : null,

                rsl_status: typeof latestRslResult !== 'undefined'
                    ? latestRslResult?.riskLevel ?? 'Unknown'
                    : 'Unknown',
            },

            route_options: routes.map(function (item, index) {
                return {
                    route_id: 'route_' + (index + 1),
                    original_index: item.originalIndex,

                    eta_minutes: roundForAi(Number(item.durationSeconds || 0) / 60, 1),
                    distance_km: roundForAi(Number(item.distanceMeters || 0) / 1000, 2),

                    eta_norm: roundForAi(item.etaNorm, 3),
                    distance_norm: roundForAi(item.distanceNorm, 3),

                    temperature_risk: roundForAi(item.tempRiskScore, 2),
                    rsl_risk: roundForAi(item.rslRiskScore, 2),

                    route_score: roundForAi(item.score, 3),
                    risk_label: item.risk?.label || 'Unknown',
                };
            }),

            lowest_score_route_id: routes.length ? 'route_1' : null,
        };
    }

    function setAiText(id, text) {
        const element = document.getElementById(id);

        if (element) {
            element.innerText = text;
        }
    }

    function setAiBadge(text, riskLevel = null) {
        const badge = document.getElementById('aiRecommendationBadge');

        if (!badge) {
            return;
        }

        badge.innerText = text;
        badge.classList.remove('good', 'warning', 'critical');

        if (riskLevel === 'safe' || riskLevel === 'good' || riskLevel === 'low') {
            badge.classList.add('good');
        }

        if (riskLevel === 'warning' || riskLevel === 'medium') {
            badge.classList.add('warning');
        }

        if (riskLevel === 'critical' || riskLevel === 'high') {
            badge.classList.add('critical');
        }
    }

    async function requestOpenAiRouteRecommendation(scoredRoutes) {
        window.ColdTraceSpeech?.clear('Waiting for the updated AI recommendation.');
        const aiRequestVersion = ++aiRecommendationRequestVersion;
        if (!latestCurrentPosition || !ColdTraceLocation.fresh(detailGpsRecordedAt)) {
            clearCurrentGpsMarker();
            setAiBadge('Waiting for live GPS', 'warning');
            setAiText('aiRecommendedAction', 'Wait for a fresh truck location before requesting a route.');
            return;
        }

        if (!Array.isArray(scoredRoutes) || scoredRoutes.length === 0) {
            setAiBadge('No route', 'warning');
            setAiText('aiRecommendedAction', 'Start navigation first before asking AI.');
            setAiText('aiReason', 'ColdTrace needs route options before OpenAI can analyze the delivery risk.');
            return;
        }

        // route_1, route_2, etc. refer to this request's sorted options, not
        // Google's original route indexes or a later set of routes.
        const routeSnapshot = [...scoredRoutes];
        const requestVersion = routeRequestVersion;
        const payload = buildAiRoutePayload(routeSnapshot);
        const requestIsCurrent = () => aiRequestVersion === aiRecommendationRequestVersion
            && requestVersion === routeRequestVersion
            && latestCurrentPosition
            && ColdTraceLocation.fresh(detailGpsRecordedAt)
            && routeSnapshot.length === latestScoredRoutes.length
            && routeSnapshot.every((route, index) => route === latestScoredRoutes[index]);

        setAiBadge('AI analyzing...', 'warning');
        setAiText('aiRecommendedAction', 'OpenAI is reviewing the route score and cargo risk.');
        setAiText('aiReason', 'Please wait while ColdTrace AI generates the recommendation.');

        try {
            const response = await fetch(aiRouteRecommendationUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json();
            if (!requestIsCurrent()) return;

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'AI recommendation failed.');
            }

            if (!applyOpenAiRouteRecommendation(result.recommendation, routeSnapshot)) {
                throw new Error('The recommendation did not match an available route.');
            }
        } catch (error) {
            if (!requestIsCurrent()) return;
            console.error('OpenAI route recommendation error:', error);

            setAiBadge('Rule-based route', 'warning');
            setAiText('aiRecommendedAction', 'Continue with the selected route.');
            setAiText('aiReason', 'AI recommendation is unavailable. The route remains based on ColdTrace scores.');
            setAiText('aiRiskReason', 'Continue monitoring cargo temperature and RSL.');
            updateSpokenRecommendation(false);
        }
    }

    function applyOpenAiRouteRecommendation(recommendation, routeSnapshot = latestScoredRoutes) {
        if (!recommendation || !latestCurrentPosition || !ColdTraceLocation.fresh(detailGpsRecordedAt)) {
            return false;
        }

        const recommendedRoute = routeSnapshot.find((route, index) =>
            recommendation.recommended_route_id === 'route_' + (index + 1));
        if (!recommendedRoute || !latestScoredRoutes.includes(recommendedRoute)) return false;

        recommendedRouteOriginalIndex = recommendedRoute.originalIndex;
        if (!selectRouteOption(recommendedRoute.originalIndex, { fromRecommendation: true })) return false;

        const isAiRecommendation = recommendation.decision_source === 'openai';
        const riskLevel = ['safe', 'warning', 'critical'].includes(recommendation.risk_level)
            ? recommendation.risk_level : 'warning';
        const riskCard = document.querySelector('.risk-card');

        if (riskCard) {
            riskCard.classList.remove('good', 'warning', 'critical');

            if (riskLevel === 'safe') {
                riskCard.classList.add('good');
            } else if (riskLevel === 'critical') {
                riskCard.classList.add('critical');
            } else {
                riskCard.classList.add('warning');
            }
        }

        setAiBadge((isAiRecommendation ? 'AI: ' : 'Rule-based: ') + riskLevel.toUpperCase(), riskLevel);

        setAiText(
            'aiRecommendedAction',
            recommendation.driver_action || 'Proceed with the recommended route.'
        );

        setAiText(
            'aiReason',
            isAiRecommendation
                ? (recommendation.reason || 'AI selected this route from the available road routes and cargo conditions.')
                : 'AI recommendation is unavailable. ColdTrace selected this route using its calculated delivery and cargo-risk scores.'
        );

        setAiText(
            'aiRisk',
            String(riskLevel).toUpperCase()
        );

        setAiText(
            'aiRiskReason',
            recommendation.cold_chain_warning || 'Continue monitoring cargo temperature and remaining shelf life.'
        );
        updateSpokenRecommendation(isAiRecommendation);
        return true;
    }

    function updateSpokenRecommendation(isAiRecommendation = false) {
        window.ColdTraceSpeech?.setRecommendation([
            isAiRecommendation ? 'AI route recommendation.' : 'ColdTrace automatic route recommendation.',
            document.getElementById('aiRecommendedAction')?.innerText,
            document.getElementById('aiReason')?.innerText,
            document.getElementById('aiRiskReason')?.innerText,
        ], () => !!latestCurrentPosition && latestScoredRoutes.length > 0 && ColdTraceLocation.fresh(detailGpsRecordedAt));
    }

    function chooseBestRoute(routes) {
        const scoredRoutes = scoreRoutes(routes);
        return scoredRoutes[0]?.originalIndex ?? 0;
    }

    function renderAlternateRoutes(scoredRoutes, selectedOriginalIndex = null) {
        const container = document.getElementById('alternateRouteList');

        if (!container) {
            return;
        }

        const topRoutes = (Array.isArray(scoredRoutes) ? scoredRoutes : []).slice(0, 3);

        if (!topRoutes.length) {
            container.innerHTML = '<div class="empty-route">Alternative routes will appear after ColdTrace calculates navigation.</div>';
            return;
        }

        const routeCards = topRoutes.map(function (item, index) {
            const route = item.route;
            const isSelected = Number(item.originalIndex) === Number(selectedOriginalIndex);
            const title = Number(item.originalIndex) === Number(recommendedRouteOriginalIndex)
                ? 'Recommended Route' : 'Alternate Route';
            const riskLabel = item.risk?.label || 'Unknown';
            const algorithmScore = Number(item.score).toFixed(3);

            return `
                <button type="button" class="alternate-route-card ${isSelected ? 'active' : ''}" onclick="selectRouteOption(${Number(item.originalIndex)})">
                    <span class="alternate-route-rank">${index + 1}</span>
                    <span class="alternate-route-body">
                        <strong>${title} ${index + 1}</strong>
                        <small>${getRouteDistanceText(route)} • ${getRouteDurationText(route)} • Score ${algorithmScore}</small>
                        <em>${riskLabel} cold-chain risk • avoids highways/tolls where possible</em>
                    </span>
                </button>
            `;
        }).join('');

        const routeNote = topRoutes.length < 3
            ? `<div class="empty-route">Google returned only ${topRoutes.length} usable route option(s) for this origin and destination.</div>`
            : '';

        container.innerHTML = routeCards + routeNote;
    }

    function selectRouteOption(originalIndex, { fromRecommendation = false } = {}) {
        if (!latestCurrentPosition || !ColdTraceLocation.fresh(detailGpsRecordedAt)) {
            clearCurrentGpsMarker();
            return false;
        }

        const selectedRoute = latestScoredRoutes.find(function (item) {
            return Number(item.originalIndex) === Number(originalIndex);
        });

        if (!selectedRoute) {
            return false;
        }

        if (!fromRecommendation) aiRecommendationRequestVersion++;
        selectedRouteOriginalIndex = selectedRoute.originalIndex;

        drawAllRoutePolylines(latestScoredRoutes, selectedRouteOriginalIndex);
        renderAlternateRoutes(latestScoredRoutes, selectedRouteOriginalIndex);
        renderRouteSteps(selectedRoute.route, selectedRoute, latestScoredRoutes.length);
        updateEtaPanel(selectedRoute.route);

        const displayIndex = latestScoredRoutes.findIndex(function (item) {
            return Number(item.originalIndex) === Number(selectedRouteOriginalIndex);
        });

        updateAiPanel(selectedRoute.route, displayIndex, latestScoredRoutes.length, selectedRoute);
        return true;
    }

    function openRouteScoreModal() {
        renderRouteScoreTable();

        const modal = document.getElementById('routeScoreModal');

        if (!modal) {
            return;
        }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeRouteScoreModal() {
        const modal = document.getElementById('routeScoreModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    function renderRouteScoreTable() {
        const tbody = document.getElementById('routeScoreTableBody');

        if (!tbody) {
            return;
        }

        if (!Array.isArray(latestScoredRoutes) || latestScoredRoutes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">Start navigation to calculate route scores.</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = latestScoredRoutes.map(function (item, index) {
            const isRecommended = Number(item.originalIndex) === Number(recommendedRouteOriginalIndex);
            const route = item.route;
            const tempRiskLabel = item.tempRisk?.label || 'Unknown';
            const rslRiskLabel = item.rslRisk?.label || 'Unknown';

            return `
                <tr class="${isRecommended ? 'recommended-row' : ''}">
                    <td>${index + 1}${isRecommended ? ' Recommended' : ''}</td>
                    <td>Route Option ${index + 1}</td>
                    <td>${getRouteDurationText(route)}</td>
                    <td>${getRouteDistanceText(route)}</td>
                    <td>${Number(item.etaNorm).toFixed(3)}</td>
                    <td>${Number(item.distanceNorm).toFixed(3)}</td>
                    <td>${Number(item.tempRiskScore).toFixed(2)} (${tempRiskLabel})</td>
                    <td>${Number(item.rslRiskScore).toFixed(2)} (${rslRiskLabel})</td>
                    <td><strong>${Number(item.score).toFixed(3)}</strong></td>
                </tr>
            `;
        }).join('');
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeRouteScoreModal();
        }
    });

    function renderRouteSteps(route, scoredRoute = null, routeCount = null) {
        const steps = route.legs?.[0]?.steps ?? [];
        const distanceText = getRouteDistanceText(route);
        const durationText = getRouteDurationText(route);
        const arrivalText = getArrivalTimeText(route);

        document.getElementById('routeSummaryTitle').innerText =
            distanceText + ' • ' + durationText;

        const scoreText = scoredRoute ? ' • ColdTrace score: ' + Number(scoredRoute.score).toFixed(3) : '';
        const routeCountText = routeCount ? ' • Showing top ' + routeCount + ' route option(s).' : '';

        document.getElementById('routeSummaryText').innerText =
            'ETA: ' + arrivalText + scoreText + routeCountText + ' • Route avoids highways and tolls where possible.';

        const container = document.getElementById('routeSteps');
        container.innerHTML = '';

        if (steps.length === 0) {
            container.innerHTML = '<div class="empty-route">Route calculated. No turn-by-turn steps returned.</div>';
            return;
        }

        steps.forEach(function (step, index) {
            const instruction = step.navigationInstruction?.instructions ?? 'Continue';
            const stepDistance = step.localizedValues?.distance?.text ?? '';
            const stepDuration = step.localizedValues?.staticDuration?.text ?? step.localizedValues?.duration?.text ?? '';

            const div = document.createElement('div');
            div.className = 'route-step';

            div.innerHTML = `
                <strong>${index + 1}. ${instruction}</strong>
                <small>${stepDistance} ${stepDuration ? '• ' + stepDuration : ''}</small>
            `;

            container.appendChild(div);
        });
    }

    function updateRoutePanelEmpty(title, text) {
        document.getElementById('routeSummaryTitle').innerText = title;
        document.getElementById('routeSummaryText').innerText = text;
        document.getElementById('routeSteps').innerHTML = `
            <div class="empty-route">${text}</div>
        `;

        clearRoutePolylines();
        renderAlternateRoutes([]);
    }

    function parseGoogleDuration(duration) {
        if (!duration) {
            return null;
        }

        return Number(String(duration).replace('s', ''));
    }

    function getRouteDurationSeconds(route) {
        return parseGoogleDuration(route.duration) ?? parseGoogleDuration(route.staticDuration);
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
            return null;
        }

        if (meters < 1000) {
            return meters + ' m';
        }

        return (meters / 1000).toFixed(1) + ' km';
    }

    function getArrivalTimeText(route) {
        const durationSeconds = getRouteDurationSeconds(route);

        if (!durationSeconds) {
            return 'N/A';
        }

        const etaDate = new Date(Date.now() + durationSeconds * 1000);

        return etaDate.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function getArrivalDateTimeText(route) {
        const durationSeconds = getRouteDurationSeconds(route);

        if (!durationSeconds) {
            return 'N/A';
        }

        const etaDate = new Date(Date.now() + durationSeconds * 1000);

        return etaDate.toLocaleString([], {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function updateEtaPanel(route) {
        const badge = document.getElementById('etaStatusBadge');

        if (!badge) {
            return;
        }

        badge.classList.remove('good', 'warning', 'critical');

        if (!route) {
            badge.innerText = 'No ETA';
            badge.classList.add('warning');

            document.getElementById('etaArrivalTime').innerText = 'N/A';
            document.getElementById('etaArrivalNote').innerText = 'Current GPS or delivery destination is missing.';
            document.getElementById('etaTravelTime').innerText = 'N/A';
            document.getElementById('etaDistance').innerText = 'N/A';
            document.getElementById('etaTraffic').innerText = 'N/A';
            document.getElementById('etaTrafficNote').innerText = 'Traffic-aware route not available.';
            return;
        }

        badge.innerText = 'ETA Active';
        badge.classList.add('good');

        document.getElementById('etaArrivalTime').innerText = getArrivalTimeText(route);
        document.getElementById('etaArrivalNote').innerText = 'Estimated arrival: ' + getArrivalDateTimeText(route);
        document.getElementById('etaTravelTime').innerText = getRouteDurationText(route);
        document.getElementById('etaDistance').innerText = getRouteDistanceText(route);
        document.getElementById('etaTraffic').innerText = getRouteDurationText(route);
        document.getElementById('etaTrafficNote').innerText = 'Routes API traffic-aware routing is active.';
    }

    function getCurrentTemperatureNumber() {
        const text = document.getElementById('liveTemperature')?.innerText || '';
        const number = parseFloat(text.replace('°C', '').trim());

        return isNaN(number) ? null : number;
    }

    function getTemperatureRiskLevel(temperature) {
        const safeMinTemp = @json($monitoredProduct?->min_temp !== null ? (float) $monitoredProduct->min_temp : null);
        const safeMaxTemp = @json($monitoredProduct?->max_temp !== null ? (float) $monitoredProduct->max_temp : null);

        if (temperature === null || safeMinTemp === null || safeMaxTemp === null) {
            return {
                label: 'Unknown',
                className: 'warning',
                reason: 'Temperature or product safe range is missing.',
                scorePenalty: 300,
                scoreValue: 0.50
            };
        }

        if (temperature < safeMinTemp) {
            return {
                label: 'Medium',
                className: 'warning',
                reason: 'Temperature is below safe range. Continue delivery but monitor cargo.',
                scorePenalty: 500,
                scoreValue: 0.50
            };
        }

        if (temperature > safeMaxTemp) {
            return {
                label: 'High',
                className: 'critical',
                reason: 'Temperature is above safe range. Fastest ETA route is recommended.',
                scorePenalty: 1200,
                scoreValue: 1.00
            };
        }

        return {
            label: 'Low',
            className: 'good',
            reason: 'Temperature is within safe product range.',
            scorePenalty: 0,
            scoreValue: 0.00
        };
    }

    function updateAiPanel(route, routeIndex, routeCount, scoredRoute = null) {
        if (!route) window.ColdTraceSpeech?.clear();
        const badge = document.getElementById('aiRecommendationBadge');
        const action = document.getElementById('aiRecommendedAction');
        const reason = document.getElementById('aiReason');
        const distance = document.getElementById('aiDistance');
        const duration = document.getElementById('aiDuration');
        const etaTime = document.getElementById('aiEtaTime');
        const risk = document.getElementById('aiRisk');
        const riskReason = document.getElementById('aiRiskReason');
        const riskCard = document.querySelector('.risk-card');

        if (!badge || !action || !reason || !distance || !duration || !etaTime || !risk || !riskReason || !riskCard) {
            return;
        }

        badge.classList.remove('good', 'warning', 'critical');
        riskCard.classList.remove('good', 'warning', 'critical');

        if (!route) {
            badge.innerText = 'No route';
            badge.classList.add('warning');
            action.innerText = 'Route cannot be calculated yet.';
            reason.innerText = 'Make sure current GPS and delivery coordinates are available.';
            distance.innerText = 'N/A';
            duration.innerText = 'N/A';
            etaTime.innerText = 'Estimated arrival will appear here';
            risk.innerText = 'N/A';
            riskReason.innerText = 'Waiting for route and telemetry data.';
            return;
        }

        const temperature = getCurrentTemperatureNumber();
        const tempRisk = getTemperatureRiskLevel(temperature);
        const rslRisk = typeof getLatestRslRiskLevel === 'function'
            ? getLatestRslRiskLevel()
            : null;
        const finalRisk = typeof chooseHigherRisk === 'function'
            ? chooseHigherRisk(tempRisk, rslRisk)
            : tempRisk;

        badge.innerText = 'Recommended';
        badge.classList.add(finalRisk.className);
        riskCard.classList.add(finalRisk.className);

        const displayRouteNumber = Number.isFinite(Number(routeIndex)) ? Number(routeIndex) + 1 : 1;
        const routeScoreText = scoredRoute ? ' ColdTrace score: ' + Number(scoredRoute.score).toFixed(3) + '.' : '';

        action.innerText = routeCount > 1
            ? `Use route option ${displayRouteNumber}.`
            : 'Use the calculated route.';

        reason.innerText = finalRisk.className === 'critical'
            ? 'ColdTrace detected high cold-chain risk, so the safest available ETA route is recommended.' + routeScoreText
            : 'ColdTrace selected the route using ETA, distance, GPS, temperature, and remaining shelf life.' + routeScoreText;

        distance.innerText = getRouteDistanceText(route);
        duration.innerText = getRouteDurationText(route);
        etaTime.innerText = 'Estimated arrival: ' + getArrivalTimeText(route);
        risk.innerText = finalRisk.label;
        riskReason.innerText = finalRisk.reason;
        updateSpokenRecommendation(false);
    }

    window.initDriverOrderMap = initDriverOrderMap;
    window.selectRouteOption = selectRouteOption;
</script>

<script
    async
    defer
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&callback=initDriverOrderMap&loading=async"
></script>
@endif

<script>
    (function initializeBackendTelemetryPolling() {
        if (!coldTracePage) {
            return;
        }

        const telemetryUrl = coldTracePage.dataset.telemetryUrl;
        const requestedInterval = Number(coldTracePage.dataset.pollInterval || 5000);
        const pollInterval = Number.isFinite(requestedInterval) && requestedInterval >= 3000
            ? requestedInterval
            : 5000;

        let activeController = null;
        let pollTimer = null;
        let lastRecordedAt = null;

        function formatTelemetryDate(value) {
            if (!value) {
                return 'No telemetry yet';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return String(value);
            }

            return new Intl.DateTimeFormat('en-PH', {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).format(date);
        }

        function applyTelemetry(data) {
            if (!data) {
                detailGpsRecordedAt = NaN;
                detailReadingRecordedAt = NaN;
                clearDetailLocation();
                setTelemetryChip('Telemetry: Waiting');
                return;
            }

            const temperature = toFiniteNumber(data.temperature);
            const latitude = toFiniteNumber(data.latitude);
            const longitude = toFiniteNumber(data.longitude);
            const mkt = toFiniteNumber(data.mkt_value);
            const rsl = toFiniteNumber(data.rsl_hours);

            const temperatureText = temperature === null
                ? 'No Data'
                : `${temperature.toFixed(2)} °C`;

            setText('liveTemperature', temperatureText);
            setText('quickTemperature', temperatureText);
            updateTemperatureCard(
                temperature,
                data.temperature_status || null,
                data.temperature_class || null
            );

            if (data.temperature_source === 'simulated') {
                setText('liveTemperatureStatus', `${data.temperature_status || 'No Data'} · simulated API data`);
            }

            setText('liveMkt', mkt === null ? 'N/A' : `${mkt.toFixed(2)} °C`);
            setText(
                'liveMktStatus',
                mkt === null
                    ? 'Waiting for enough telemetry data'
                    : 'Calculated from saved trip temperature history'
            );

            updateRslPanels(rsl);

            const tripOpen = ['pending', 'in_progress'].includes(data.trip_status);
            detailGpsRecordedAt = tripOpen && ColdTraceLocation.valid(latitude, longitude)
                ? ColdTraceLocation.time(data.gps_recorded_at) : NaN;
            detailReadingRecordedAt = ColdTraceLocation.time(data.recorded_at);
            if (tripOpen && ColdTraceLocation.fresh(detailGpsRecordedAt) && ColdTraceLocation.valid(latitude, longitude)) {
                const gpsText = `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`;

                setText('liveGps', gpsText);
                const locationLabel = data.location_source === 'software'
                    ? 'Live device/API location'
                    : 'Verified ESP32 location';
                const gpsAge = Number(data.gps_age_seconds);

                setText('liveGpsStatus', Number.isFinite(gpsAge)
                    ? `${locationLabel} · ${Math.round(gpsAge)}s ago`
                    : locationLabel);
                setText('liveLocationName', locationLabel);
                setText('liveLocationCoords', gpsText);

                if (typeof updateCurrentGpsMarker === 'function') updateCurrentGpsMarker(latitude, longitude);
            } else {
                clearDetailLocation();
            }

            setText('liveLastReading', formatTelemetryDate(data.recorded_at));
            setTelemetryChip(
                !tripOpen ? 'Telemetry: Trip closed' : !ColdTraceLocation.fresh(detailReadingRecordedAt) ? 'Telemetry: No recent readings'
                    : data.temperature_source === 'simulated' ? 'Telemetry: Demo API' : 'Telemetry: Connected',
                tripOpen && ColdTraceLocation.fresh(detailReadingRecordedAt) ? 'connected' : null
            );

            const readingChanged = data.recorded_at && data.recorded_at !== lastRecordedAt;
            lastRecordedAt = data.recorded_at || lastRecordedAt;

            window.dispatchEvent(new CustomEvent('coldtrace:telemetry-updated', {
                detail: data
            }));

            if (
                readingChanged && tripOpen && ColdTraceLocation.fresh(detailGpsRecordedAt)
                && typeof scoreRoutes === 'function'
                && Array.isArray(latestScoredRoutes)
                && latestScoredRoutes.length
            ) {
                const rescoredRoutes = scoreRoutes(
                    latestScoredRoutes.map(function (item) {
                        return item.route;
                    })
                ).slice(0, 3);

                if (rescoredRoutes.length) {
                    latestScoredRoutes = rescoredRoutes;
                    selectedRouteOriginalIndex = rescoredRoutes[0].originalIndex;

                    drawAllRoutePolylines(rescoredRoutes, selectedRouteOriginalIndex);
                    renderAlternateRoutes(rescoredRoutes, selectedRouteOriginalIndex);
                    renderRouteSteps(
                        rescoredRoutes[0].route,
                        rescoredRoutes[0],
                        rescoredRoutes.length
                    );
                    updateAiPanel(
                        rescoredRoutes[0].route,
                        0,
                        rescoredRoutes.length,
                        rescoredRoutes[0]
                    );
                }
            }
        }

        async function fetchLatestTelemetry() {
            if (!telemetryUrl) {
                setTelemetryChip('Telemetry: Missing URL', 'error');
                return;
            }

            activeController?.abort();
            activeController = new AbortController();

            try {
                const response = await fetch(telemetryUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: activeController.signal,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Telemetry request failed with status ${response.status}.`);
                }

                const result = await response.json();
                applyTelemetry(result.data ?? null);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                console.error('Unable to refresh telemetry:', error);
                setTelemetryChip('Telemetry: Offline', 'error');
            }
        }

        fetchLatestTelemetry();
        pollTimer = window.setInterval(fetchLatestTelemetry, pollInterval);

        window.addEventListener('beforeunload', function () {
            activeController?.abort();

            if (pollTimer) {
                window.clearInterval(pollTimer);
            }
        });
    })();
</script>
@endpush
