@extends('layouts.app')

@section('title', 'Assigned Order Location')

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

    $currentLat = $hasCurrentGps ? (float) $latestTelemetry?->latitude : null;
    $currentLng = $hasCurrentGps ? (float) $latestTelemetry?->longitude : null;

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

    if ($latestTelemetry && $monitoredProduct) {
        if ($latestTelemetry->temperature < $monitoredProduct->min_temp) {
            $temperatureClass = 'warning';
            $temperatureStatus = 'Too Low';
        } elseif ($latestTelemetry->temperature > $monitoredProduct->max_temp) {
            $temperatureClass = 'critical';
            $temperatureStatus = 'Too High';
        } else {
            $temperatureClass = 'safe';
            $temperatureStatus = 'Safe';
        }
    }

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
    class="driver-order-show-page"
    id="driverOrderPage"
    data-telemetry-url="{{ route('driver.orders.telemetry.latest', $order) }}"
    data-poll-interval="5000"
>

    <section class="hero-panel">
        <div class="hero-left">
            <span class="eyebrow">Assigned Order</span>

            <h1>{{ $order->order_code }}</h1>

            <p>
                Monitor the assigned vehicle, backend-processed cold-chain indicators, ETA,
                route guidance, and ColdTrace AI recommendations in one driver workspace.
            </p>

            <div class="hero-tags">
                <span class="status-badge status-{{ $order->status }}">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>

                <span class="soft-chip">
                    {{ $order->orderItems->count() }} product item(s)
                </span>

                <span class="soft-chip" id="telemetryConnectionChip">
                    Telemetry: Waiting
                </span>
            </div>
        </div>

        <div class="hero-actions" aria-label="Driver quick actions">
            <a href="{{ route('driver.orders.index') }}" class="secondary-button">
                <i class="bi bi-arrow-left"></i>
                Back
            </a>

            @if ($receiverPhoneLink)
                <a href="tel:{{ $receiverPhoneLink }}" class="secondary-button">
                    <i class="bi bi-telephone"></i>
                    Call Receiver
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
                Start Route
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

        <div class="quick-card temperature {{ $temperatureClass }}">
            <span>Cargo Temperature</span>
            <strong id="quickTemperature">
                {{ $temperatureValue !== null ? $temperatureValue . ' °C' : 'No Data' }}
            </strong>
            <small id="quickTemperatureStatus">{{ $temperatureStatus }}</small>
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
                <span class="section-kicker">Live Telemetry</span>
                <h2>Cold-Chain Health and Vehicle Position</h2>
                <p>
                    Temperature, MKT, and remaining shelf life are read from the Laravel backend, while GPS keeps the active route current.
                </p>
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
                    <small id="liveTemperatureStatus">{{ $temperatureStatus }}</small>
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
                        {{ $hasCurrentGps ? 'Latest saved vehicle position' : 'Waiting for ESP32 GPS signal' }}
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
                <span class="section-kicker">ETA Tracking</span>
                <h2>Estimated Arrival</h2>
                <p>
                    ETA is calculated inside ColdTrace using the current ESP32 GPS location and delivery destination.
                </p>
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

    <section class="ai-route-panel">
        <div class="section-title-row">
            <div>
                <span class="section-kicker">AI Route Recommendation</span>
                <h2>ColdTrace Route Decision</h2>
                <p>
                    ColdTrace evaluates GPS availability, distance, travel time, ETA,
                    cargo temperature, backend-calculated MKT, and remaining shelf-life risk.
                </p>
            </div>

            <div class="ai-header-actions">
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

        <div class="ai-grid">
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
                <span class="section-kicker">In-Page Navigation</span>
                <h2>Live Route Map</h2>
                <p>
                    The driver stays inside ColdTrace. Only one vehicle marker, delivery point, ETA, and route guidance are shown here.
                </p>
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

    <div class="content-grid">
        <section class="details-panel">
            <div class="section-title-row compact">
                <div>
                    <span class="section-kicker">Order Info</span>
                    <h2>Delivery Details</h2>
                </div>
            </div>

            <div class="details-grid">
                <div class="detail-card">
                    <span>Receiver</span>
                    <strong>{{ $order->receiver?->name ?? 'N/A' }}</strong>
                    <small>{{ $order->receiver?->phone ?? $order->receiver?->email ?? 'No contact' }}</small>
                </div>

                <div class="detail-card">
                    <span>Expected Delivery</span>
                    <strong>{{ $order->expected_delivery_at?->format('M d, Y') ?? 'Not set' }}</strong>
                    <small>{{ $order->expected_delivery_at?->format('h:i A') ?? 'No time provided' }}</small>
                </div>

                <div class="detail-card">
                    <span>Status</span>
                    <strong>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</strong>
                    <small>Assigned to your driver account</small>
                </div>

                <div class="detail-card">
                    <span>Created By</span>
                    <strong>{{ $order->creator?->name ?? 'N/A' }}</strong>
                    <small>{{ $order->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</small>
                </div>

                <div class="detail-card full-width">
                    <span>Handling Notes</span>
                    <strong>{{ $order->notes ?: 'No notes provided.' }}</strong>
                </div>
            </div>
        </section>

        <section class="products-panel">
            <div class="section-title-row compact">
                <div>
                    <span class="section-kicker">Cargo</span>
                    <h2>Products</h2>
                </div>
            </div>

            <div class="product-list">
                @forelse ($order->orderItems as $item)
                    <div class="product-row">
                        <div>
                            <strong>{{ $item->product?->name ?? 'N/A' }}</strong>

                            <small>
                                Safe range:
                                {{ $item->product?->min_temp ?? 'N/A' }}°C
                                to
                                {{ $item->product?->max_temp ?? 'N/A' }}°C
                            </small>
                        </div>

                        <em>{{ $item->quantity }} {{ $item->unit }}</em>
                    </div>
                @empty
                    <div class="empty-box">
                        No products listed.
                    </div>
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

@push('styles')
<style>
    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    .driver-order-show-page {
        --ct-blue: #2563eb;
        --ct-cyan: #06b6d4;
        --ct-green: #16a34a;
        --ct-amber: #f59e0b;
        --ct-red: #dc2626;
        --ct-slate-50: #f8fafc;
        --ct-slate-100: #f1f5f9;
        --ct-slate-200: #e2e8f0;
        --ct-slate-300: #cbd5e1;
        --ct-slate-500: #64748b;
        --ct-slate-600: #475569;
        --ct-slate-900: #0f172a;
        display: flex;
        flex-direction: column;
        gap: clamp(16px, 2vw, 22px);
        width: 100%;
        max-width: 100%;
        color: var(--ct-slate-900);
    }

    .driver-order-show-page button,
    .driver-order-show-page a,
    .driver-order-show-page input,
    .driver-order-show-page select,
    .driver-order-show-page textarea {
        -webkit-tap-highlight-color: transparent;
    }

    .hero-panel,
    .driver-quick-summary,
    .live-panel,
    .map-panel,
    .details-panel,
    .products-panel,
    .raw-panel,
    .ai-route-panel,
    .eta-panel {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: clamp(20px, 2.4vw, 28px);
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    }

    .hero-panel {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: end;
        gap: 22px;
        padding: clamp(20px, 2.4vw, 28px);
        background:
            radial-gradient(circle at top left, rgba(34, 211, 238, 0.22), transparent 38%),
            linear-gradient(135deg, #ffffff, #f8fafc);
    }

    .hero-left {
        min-width: 0;
    }

    .eyebrow,
    .section-kicker {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .eyebrow {
        background: #ecfeff;
        color: #0891b2;
        border: 1px solid #cffafe;
        padding: 7px 12px;
        margin-bottom: 12px;
    }

    .section-kicker {
        color: var(--ct-blue);
        margin-bottom: 8px;
    }

    .hero-panel h1 {
        margin: 0;
        color: var(--ct-slate-900);
        font-size: clamp(28px, 5vw, 38px);
        line-height: 1.04;
        font-weight: 950;
        letter-spacing: -0.055em;
        overflow-wrap: anywhere;
    }

    .hero-panel p {
        margin: 12px 0 0;
        color: var(--ct-slate-500);
        line-height: 1.65;
        max-width: 760px;
    }

    .hero-tags,
    .hero-actions,
    .map-legend {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .hero-tags {
        margin-top: 18px;
    }

    .hero-actions {
        justify-content: flex-end;
        min-width: 300px;
    }

    .primary-button,
    .secondary-button,
    .copy-location-button,
    .mobile-driver-action-bar a,
    .mobile-driver-action-bar button {
        border: 0;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 46px;
        border-radius: 999px;
        padding: 0 18px;
        font-weight: 950;
        font-size: 14px;
        transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
        white-space: nowrap;
    }

    .primary-button {
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        box-shadow: 0 12px 24px rgba(37, 99, 235, 0.22);
    }

    .secondary-button {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid #e2e8f0;
    }

    .primary-button:active,
    .secondary-button:active,
    .copy-location-button:active,
    .mobile-driver-action-bar a:active,
    .mobile-driver-action-bar button:active {
        transform: scale(0.98);
    }

    .soft-chip,
    .panel-chip,
    .ai-badge,
    .eta-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: var(--ct-slate-50);
        color: var(--ct-slate-600);
        border: 1px solid var(--ct-slate-200);
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        max-width: 100%;
        overflow-wrap: anywhere;
    }

    .ai-badge.good,
    .eta-badge.good {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }

    .ai-badge.warning,
    .eta-badge.warning {
        background: #fffbeb;
        color: #d97706;
        border-color: #fde68a;
    }

    .ai-badge.critical,
    .eta-badge.critical {
        background: #fef2f2;
        color: var(--ct-red);
        border-color: #fecaca;
    }

    #telemetryConnectionChip.connected {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }

    #telemetryConnectionChip.error {
        background: #fef2f2;
        color: var(--ct-red);
        border-color: #fecaca;
    }

    .driver-quick-summary {
        display: grid;
        grid-template-columns: 1.25fr 0.9fr 0.9fr 0.9fr;
        gap: 14px;
        padding: 16px;
    }

    .quick-card,
    .telemetry-card,
    .ai-card,
    .eta-card,
    .location-card,
    .detail-card,
    .product-row {
        min-width: 0;
    }

    .quick-card {
        border-radius: 22px;
        border: 1px solid #e5e7eb;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 45%),
            #f8fafc;
        padding: 16px;
    }

    .quick-card.destination {
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.11), transparent 45%),
            #eff6ff;
        border-color: #bfdbfe;
    }

    .quick-card.receiver {
        background:
            radial-gradient(circle at top right, rgba(6, 182, 212, 0.11), transparent 45%),
            #ecfeff;
        border-color: #a5f3fc;
    }

    .quick-card.schedule {
        background:
            radial-gradient(circle at top right, rgba(245, 158, 11, 0.10), transparent 45%),
            #fffbeb;
        border-color: #fde68a;
    }

    .quick-card.temperature.safe {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .quick-card.temperature.warning {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .quick-card.temperature.critical {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .quick-card span,
    .telemetry-card span,
    .ai-card span,
    .eta-card span,
    .location-card span,
    .detail-card span {
        display: block;
        color: var(--ct-slate-500);
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .quick-card strong,
    .telemetry-card strong,
    .ai-card strong,
    .eta-card strong,
    .location-card strong,
    .detail-card strong {
        display: block;
        color: var(--ct-slate-900);
        font-weight: 950;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .quick-card strong {
        font-size: 16px;
    }

    .quick-card small,
    .telemetry-card small,
    .ai-card small,
    .eta-card small,
    .location-card small,
    .detail-card small {
        display: block;
        margin-top: 7px;
        color: var(--ct-slate-500);
        font-size: 12px;
        font-weight: 800;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .live-panel,
    .map-panel,
    .details-panel,
    .products-panel,
    .raw-panel,
    .ai-route-panel,
    .eta-panel {
        padding: clamp(18px, 2vw, 24px);
    }

    .section-title-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 18px;
    }

    .section-title-row.compact {
        margin-bottom: 14px;
    }

    .section-title-row h2 {
        margin: 0;
        color: var(--ct-slate-900);
        font-size: clamp(20px, 2.6vw, 22px);
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .section-title-row p {
        margin: 6px 0 0;
        color: var(--ct-slate-500);
        line-height: 1.6;
    }

    .last-reading-card {
        min-width: 230px;
        border-radius: 18px;
        background: var(--ct-slate-50);
        border: 1px solid #e5e7eb;
        padding: 13px 15px;
        text-align: right;
    }

    .last-reading-card span {
        display: block;
        color: var(--ct-slate-500);
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 5px;
    }

    .last-reading-card strong {
        color: var(--ct-slate-900);
        font-size: 13px;
        font-weight: 900;
    }

    .telemetry-grid,
    .ai-grid,
    .eta-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .telemetry-card,
    .ai-card,
    .eta-card {
        min-height: 150px;
        border-radius: 24px;
        border: 1px solid #e5e7eb;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 45%),
            #f8fafc;
        padding: 18px;
    }

    .telemetry-card {
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }



    .telemetry-copy {
        min-width: 0;
        flex: 1;
    }

    .telemetry-card {
        position: relative;
        overflow: hidden;
        isolation: isolate;
    }

    .telemetry-card::after {
        content: '';
        position: absolute;
        width: 110px;
        height: 110px;
        right: -48px;
        bottom: -58px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.48);
        z-index: -1;
    }

    .telemetry-card.mkt-card {
        background:
            radial-gradient(circle at top right, rgba(124, 58, 237, 0.13), transparent 44%),
            #faf5ff;
        border-color: #ddd6fe;
    }

    .telemetry-card.mkt-card .telemetry-icon {
        background: linear-gradient(135deg, #7c3aed, #8b5cf6);
        box-shadow: 0 12px 22px rgba(124, 58, 237, 0.2);
    }

    .telemetry-card.rsl-card.good,
    .ai-rsl-card.good {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .telemetry-card.rsl-card.good .telemetry-icon {
        background: linear-gradient(135deg, #16a34a, #15803d);
    }

    .telemetry-card.rsl-card.warning,
    .ai-rsl-card.warning {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .telemetry-card.rsl-card.warning .telemetry-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .telemetry-card.rsl-card.critical,
    .ai-rsl-card.critical {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .telemetry-card.rsl-card.critical .telemetry-icon {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }

    .telemetry-card.rsl-card.neutral {
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    .telemetry-card.rsl-card.good strong,
    .telemetry-card.rsl-card.good small,
    .ai-rsl-card.good strong,
    .ai-rsl-card.good small {
        color: #15803d;
    }

    .telemetry-card.rsl-card.warning strong,
    .telemetry-card.rsl-card.warning small,
    .ai-rsl-card.warning strong,
    .ai-rsl-card.warning small {
        color: #b45309;
    }

    .telemetry-card.rsl-card.critical strong,
    .telemetry-card.rsl-card.critical small,
    .ai-rsl-card.critical strong,
    .ai-rsl-card.critical small {
        color: #dc2626;
    }

    .eta-card {
        background:
            radial-gradient(circle at top right, rgba(6, 182, 212, 0.10), transparent 45%),
            #f8fafc;
    }

    .telemetry-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 17px;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        box-shadow: 0 12px 22px rgba(37, 99, 235, 0.22);
    }

    .telemetry-card strong,
    .ai-card strong,
    .eta-card strong {
        font-size: clamp(19px, 2.6vw, 22px);
    }

    .telemetry-card.safe,
    .quick-card.temperature.safe {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .telemetry-card.safe .telemetry-icon {
        background: linear-gradient(135deg, #16a34a, #15803d);
    }

    .telemetry-card.safe strong,
    .telemetry-card.safe small,
    .quick-card.temperature.safe strong,
    .quick-card.temperature.safe small {
        color: #15803d;
    }

    .telemetry-card.warning,
    .quick-card.temperature.warning {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .telemetry-card.warning .telemetry-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .telemetry-card.warning strong,
    .telemetry-card.warning small,
    .quick-card.temperature.warning strong,
    .quick-card.temperature.warning small {
        color: #d97706;
    }

    .telemetry-card.critical,
    .quick-card.temperature.critical {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .telemetry-card.critical .telemetry-icon {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }

    .telemetry-card.critical strong,
    .telemetry-card.critical small,
    .quick-card.temperature.critical strong,
    .quick-card.temperature.critical small {
        color: var(--ct-red);
    }

    .risk-card.warning {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .risk-card.critical {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .risk-card.good {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .ai-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .route-score-button {
        min-height: 38px;
        padding: 0 14px;
        font-size: 12px;
    }

    .route-score-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }

    .route-score-modal.show {
        display: flex;
    }

    .route-score-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        backdrop-filter: blur(6px);
    }

    .route-score-dialog {
        position: relative;
        z-index: 1;
        width: min(1100px, 100%);
        max-height: min(86vh, 760px);
        overflow: hidden;
        border-radius: 26px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.35);
        display: flex;
        flex-direction: column;
    }

    .route-score-header {
        padding: 22px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }

    .route-score-header h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 950;
        color: #0f172a;
        letter-spacing: -0.04em;
    }

    .route-score-header p {
        margin: 8px 0 0;
        color: #64748b;
        font-weight: 750;
        line-height: 1.55;
    }

    .route-score-close {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #0f172a;
        cursor: pointer;
    }

    .formula-box {
        margin: 18px 22px 0;
        padding: 16px;
        border-radius: 20px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
    }

    .formula-box strong {
        display: block;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .formula-box p {
        margin: 0;
        color: #0f172a;
        font-weight: 900;
        line-height: 1.6;
    }

    .formula-box small {
        display: block;
        margin-top: 8px;
        color: #475569;
        font-weight: 800;
        line-height: 1.55;
    }

    .route-score-table-wrap {
        margin: 18px 22px 22px;
        overflow: auto;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
    }

    .route-score-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 920px;
        background: #ffffff;
    }

    .route-score-table th,
    .route-score-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        font-size: 13px;
    }

    .route-score-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .route-score-table td {
        color: #0f172a;
        font-weight: 850;
    }

    .route-score-table tr.recommended-row td {
        background: #f0fdf4;
        color: #15803d;
    }

    .location-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }

    .location-card,
    .detail-card {
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        padding: 16px;
    }

    .location-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 4px;
    }

    .location-card-top span {
        margin-bottom: 0;
    }

    .copy-location-button {
        min-height: 34px;
        padding: 0 12px;
        font-size: 12px;
        background: #ffffff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
        box-shadow: none;
    }


    .current-card {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .delivery-card {
        background: #eff6ff;
        border-color: #bfdbfe;
    }

    .navigation-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 360px);
        gap: 16px;
        align-items: stretch;
    }

    #driverOrderMap {
        width: 100%;
        min-height: 360px;
        height: min(68vh, 620px);
        border-radius: 24px;
        overflow: hidden;
        background: #e2e8f0;
        border: 1px solid #e5e7eb;
        touch-action: pan-x pan-y;
    }

    .route-panel {
        height: min(68vh, 620px);
        min-height: 360px;
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
    }

    .route-panel-header {
        padding: 18px;
        border-bottom: 1px solid #e5e7eb;
        background: #ffffff;
        flex-shrink: 0;
    }

    .route-panel-header span {
        display: block;
        color: #2563eb;
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }

    .route-panel-header strong {
        display: block;
        color: #0f172a;
        font-size: 17px;
        font-weight: 950;
        overflow-wrap: anywhere;
    }

    .route-panel-header small {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        margin-top: 5px;
        overflow-wrap: anywhere;
    }

    .alternate-route-section {
        padding: 14px;
        border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
        flex-shrink: 0;
    }

    .alternate-route-section-title {
        display: grid;
        gap: 4px;
        margin-bottom: 10px;
    }

    .alternate-route-section-title span {
        color: #2563eb;
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .alternate-route-section-title small {
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.45;
    }

    .alternate-route-list {
        display: grid;
        gap: 9px;
    }

    .alternate-route-card {
        width: 100%;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        border-radius: 16px;
        padding: 12px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        text-align: left;
        cursor: pointer;
    }

    .alternate-route-card.active {
        background: #eff6ff;
        border-color: #bfdbfe;
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.12);
    }

    .alternate-route-rank {
        width: 30px;
        height: 30px;
        min-width: 30px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        font-size: 12px;
        font-weight: 950;
    }

    .alternate-route-card:not(.active) .alternate-route-rank {
        background: #e2e8f0;
        color: #0f172a;
    }

    .alternate-route-body {
        min-width: 0;
        display: block;
    }

    .alternate-route-body strong {
        display: block;
        color: #0f172a;
        font-size: 13px;
        font-weight: 950;
        overflow-wrap: anywhere;
    }

    .alternate-route-body small,
    .alternate-route-body em {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-style: normal;
        font-weight: 800;
        line-height: 1.45;
        margin-top: 4px;
        overflow-wrap: anywhere;
    }

    .alternate-route-body em {
        color: #2563eb;
    }

    .route-steps {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .route-step {
        padding: 14px;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
    }

    .route-step strong {
        display: block;
        color: #0f172a;
        font-size: 13px;
        font-weight: 900;
        line-height: 1.45;
    }

    .route-step small {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 750;
        margin-top: 6px;
    }

    .empty-route {
        padding: 18px;
        border-radius: 18px;
        background: #ffffff;
        color: #64748b;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.6;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        display: inline-flex;
        margin-right: 6px;
    }


    .legend-dot.current {
        background: #16a34a;
    }

    .legend-dot.delivery {
        background: #2563eb;
    }

    .map-legend span {
        color: #475569;
        font-size: 12px;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
    }

    .content-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
        gap: 22px;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .detail-card.full-width {
        grid-column: 1 / -1;
    }

    .product-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .product-row {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: center;
        padding: 14px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
    }

    .product-row strong {
        display: block;
        color: #0f172a;
        font-size: 15px;
        font-weight: 950;
        overflow-wrap: anywhere;
    }

    .product-row small {
        display: block;
        color: #64748b;
        margin-top: 5px;
        font-size: 12px;
        font-weight: 750;
    }

    .product-row em {
        color: #2563eb;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-style: normal;
        font-weight: 950;
        white-space: nowrap;
    }

    .status-badge {
        display: inline-flex;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    .status-pending {
        background: #fffbeb;
        color: #d97706;
    }

    .status-approved {
        background: #eff6ff;
        color: #2563eb;
    }

    .status-assigned,
    .status-in_transit {
        background: #ecfeff;
        color: #0891b2;
    }

    .status-delivered {
        background: #f0fdf4;
        color: #15803d;
    }

    .status-cancelled {
        background: #fef2f2;
        color: #dc2626;
    }

    .warning-box {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
        padding: 14px 16px;
        border-radius: 16px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.5;
    }

    .empty-box {
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        color: #64748b;
        padding: 18px;
        font-weight: 800;
    }

    .raw-panel {
        padding: 0;
        overflow: hidden;
    }

    .debug-details {
        padding: 0;
    }

    .debug-details summary {
        list-style: none;
        cursor: pointer;
        padding: 18px 22px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 4px;
        background: #ffffff;
    }

    .debug-details summary::-webkit-details-marker {
        display: none;
    }

    .debug-details summary span {
        color: #2563eb;
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .debug-details summary strong {
        color: #0f172a;
        font-size: 17px;
        font-weight: 950;
    }

    .debug-details summary small {
        color: #64748b;
        font-weight: 750;
    }

    #liveRawJson {
        margin: 0 18px 18px;
        background: #0f172a;
        color: #22c55e;
        border-radius: 18px;
        padding: 18px;
        font-size: 13px;
        line-height: 1.6;
        overflow-x: auto;
        min-height: 160px;
        max-height: 420px;
    }

    .mobile-driver-action-bar {
        display: none;
    }

    @media (max-width: 1240px) {
        .driver-quick-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .navigation-layout {
            grid-template-columns: 1fr;
        }

        .route-panel {
            height: auto;
            max-height: 520px;
        }

        #driverOrderMap {
            height: 520px;
        }
    }

    @media (max-width: 1180px) {
        .telemetry-grid,
        .ai-grid,
        .eta-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 960px) {
        .hero-panel {
            grid-template-columns: 1fr;
            align-items: start;
        }

        .hero-actions {
            justify-content: flex-start;
            min-width: 0;
            width: 100%;
        }

        .section-title-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .last-reading-card {
            text-align: left;
            width: 100%;
            min-width: 0;
        }

        .location-grid,
        .details-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .driver-order-show-page {
            padding-bottom: calc(86px + env(safe-area-inset-bottom));
            gap: 16px;
        }

        .hero-panel,
        .driver-quick-summary,
        .live-panel,
        .map-panel,
        .details-panel,
        .products-panel,
        .ai-route-panel,
        .eta-panel {
            border-radius: 22px;
        }

        .hero-panel,
        .live-panel,
        .map-panel,
        .details-panel,
        .products-panel,
        .ai-route-panel,
        .eta-panel {
            padding: 18px;
        }

        .hero-panel p,
        .section-title-row p {
            font-size: 14px;
        }

        .hero-actions .primary-button,
        .hero-actions .secondary-button {
            flex: 1 1 calc(50% - 8px);
            min-width: 140px;
        }

        .driver-quick-summary,
        .telemetry-grid,
        .ai-grid,
        .eta-grid {
            grid-template-columns: 1fr;
        }

        .quick-card,
        .telemetry-card,
        .ai-card,
        .eta-card,
        .location-card,
        .detail-card,
        .product-row {
            border-radius: 18px;
        }

        .telemetry-card,
        .ai-card,
        .eta-card {
            min-height: 0;
            padding: 16px;
        }

        .telemetry-card {
            align-items: center;
        }

        .telemetry-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 15px;
            font-size: 20px;
        }

        .map-legend {
            width: 100%;
        }

        .ai-header-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .route-score-dialog {
            max-height: 90vh;
            border-radius: 20px;
        }

        .route-score-header {
            padding: 18px;
        }

        .formula-box,
        .route-score-table-wrap {
            margin-left: 18px;
            margin-right: 18px;
        }

        #driverOrderMap {
            height: 430px;
            border-radius: 20px;
        }

        .route-panel {
            min-height: 0;
            max-height: none;
            border-radius: 20px;
        }

        .route-steps {
            max-height: 360px;
        }

        .product-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .product-row em {
            white-space: normal;
        }

        .debug-details summary {
            padding: 16px 18px;
        }

        #liveRawJson {
            margin: 0 14px 14px;
            max-height: 320px;
            font-size: 12px;
        }

        .mobile-driver-action-bar {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: calc(12px + env(safe-area-inset-bottom));
            z-index: 999;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(92px, 1fr));
            gap: 8px;
            padding: 10px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.22);
            backdrop-filter: blur(12px);
        }

        .mobile-driver-action-bar a,
        .mobile-driver-action-bar button {
            min-height: 50px;
            padding: 0 10px;
            border-radius: 18px;
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            flex-direction: column;
            gap: 4px;
        }

        .mobile-driver-action-bar button {
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.22);
        }
    }

    @media (max-width: 480px) {
        .hero-panel,
        .driver-quick-summary,
        .live-panel,
        .map-panel,
        .details-panel,
        .products-panel,
        .raw-panel,
        .ai-route-panel,
        .eta-panel {
            border-radius: 18px;
        }

        .hero-panel,
        .live-panel,
        .map-panel,
        .details-panel,
        .products-panel,
        .ai-route-panel,
        .eta-panel {
            padding: 15px;
        }

        .driver-quick-summary {
            padding: 12px;
        }

        .hero-panel h1 {
            font-size: 26px;
        }

        .hero-actions .primary-button,
        .hero-actions .secondary-button {
            flex-basis: 100%;
            width: 100%;
        }

        .hero-tags,
        .map-legend {
            gap: 8px;
        }

        .soft-chip,
        .status-badge,
        .ai-badge,
        .eta-badge {
            font-size: 11px;
            padding: 6px 9px;
        }

        .section-title-row h2 {
            font-size: 19px;
        }

        .quick-card strong,
        .location-card strong,
        .detail-card strong {
            font-size: 14px;
        }

        .telemetry-card strong,
        .ai-card strong,
        .eta-card strong {
            font-size: 18px;
        }

        #driverOrderMap {
            height: 360px;
        }

        .route-step,
        .empty-route {
            padding: 13px;
        }

        .mobile-driver-action-bar {
            left: 8px;
            right: 8px;
            bottom: calc(8px + env(safe-area-inset-bottom));
            border-radius: 20px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .primary-button,
        .secondary-button,
        .copy-location-button,
        .mobile-driver-action-bar a,
        .mobile-driver-action-bar button {
            transition: none;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const coldTracePage = document.getElementById('driverOrderPage');
    const initialShelfLifeHours = Number(@json($initialShelfLifeHours));
    const safeMinTemp = @json($monitoredProduct?->min_temp !== null ? (float) $monitoredProduct->min_temp : null);
    const safeMaxTemp = @json($monitoredProduct?->max_temp !== null ? (float) $monitoredProduct->max_temp : null);

    let latestRslResult = null;

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
    let AdvancedMarkerElementClass = null;
    let latestCurrentPosition = null;

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
        latestCurrentPosition = initialCurrentPosition;

        const fallbackPosition = initialCurrentPosition || initialDeliveryPosition || {
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

        if (initialCurrentPosition) {
            currentTruckMarker = createMapMarker(initialCurrentPosition, 'current', 'Current Vehicle / ESP32 Location');
            coldTraceBounds.extend(initialCurrentPosition);
        }

        if (initialCurrentPosition || initialDeliveryPosition) {
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

    if (type === 'current') {
        marker.innerHTML = '🚌';
        marker.style.background = 'linear-gradient(135deg, #16a34a, #15803d)';
    } else {
        marker.innerHTML = '📍';

        marker.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
    }

    return marker;
}

    function updateCurrentGpsMarker(lat, lng) {
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

        calculateInternalRoute(false);
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
        if (!Array.isArray(scoredRoutes) || scoredRoutes.length === 0) {
            setAiBadge('No route', 'warning');
            setAiText('aiRecommendedAction', 'Start navigation first before asking AI.');
            setAiText('aiReason', 'ColdTrace needs route options before OpenAI can analyze the delivery risk.');
            return;
        }

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
                body: JSON.stringify(buildAiRoutePayload(scoredRoutes)),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'AI recommendation failed.');
            }

            applyOpenAiRouteRecommendation(result.recommendation);
        } catch (error) {
            console.error('OpenAI route recommendation error:', error);

            setAiBadge('Weighted score used', 'warning');
            setAiText('aiRecommendedAction', 'Proceed using the lowest ColdTrace route score.');
            setAiText('aiReason', 'AI explanation is temporarily unavailable. The weighted scoring rule is still active.');
            setAiText('aiRiskReason', 'Continue monitoring cargo temperature and RSL.');
        }
    }

    function applyOpenAiRouteRecommendation(recommendation) {
        if (!recommendation) {
            return;
        }

        const riskLevel = recommendation.risk_level || 'warning';
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

        setAiBadge('AI: ' + String(riskLevel).toUpperCase(), riskLevel);

        setAiText(
            'aiRecommendedAction',
            recommendation.driver_action || 'Proceed with the recommended route.'
        );

        setAiText(
            'aiReason',
            recommendation.reason || 'ColdTrace AI analyzed ETA, distance, temperature risk, and RSL risk.'
        );

        setAiText(
            'aiRisk',
            String(riskLevel).toUpperCase()
        );

        setAiText(
            'aiRiskReason',
            recommendation.cold_chain_warning || 'Continue monitoring cargo temperature and remaining shelf life.'
        );
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
            const title = index === 0 ? 'Recommended Route' : 'Alternate Route';
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

    function selectRouteOption(originalIndex) {
        const selectedRoute = latestScoredRoutes.find(function (item) {
            return Number(item.originalIndex) === Number(originalIndex);
        });

        if (!selectedRoute) {
            return;
        }

        selectedRouteOriginalIndex = selectedRoute.originalIndex;

        drawAllRoutePolylines(latestScoredRoutes, selectedRouteOriginalIndex);
        renderAlternateRoutes(latestScoredRoutes, selectedRouteOriginalIndex);
        renderRouteSteps(selectedRoute.route, selectedRoute, latestScoredRoutes.length);
        updateEtaPanel(selectedRoute.route);

        const displayIndex = latestScoredRoutes.findIndex(function (item) {
            return Number(item.originalIndex) === Number(selectedRouteOriginalIndex);
        });

        updateAiPanel(selectedRoute.route, displayIndex, latestScoredRoutes.length, selectedRoute);
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
            const isRecommended = index === 0;
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
        let lastGpsKey = null;

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

            setText('liveMkt', mkt === null ? 'N/A' : `${mkt.toFixed(2)} °C`);
            setText(
                'liveMktStatus',
                mkt === null
                    ? 'Waiting for enough telemetry data'
                    : 'Calculated from saved trip temperature history'
            );

            updateRslPanels(rsl);

            if (latitude !== null && longitude !== null) {
                const gpsText = `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`;

                setText('liveGps', gpsText);
                setText('liveGpsStatus', 'Latest saved vehicle position');
                setText('liveLocationName', 'Vehicle / ESP32 Location');
                setText('liveLocationCoords', gpsText);

                const gpsKey = `${latitude.toFixed(7)},${longitude.toFixed(7)}`;

                if (gpsKey !== lastGpsKey && typeof updateCurrentGpsMarker === 'function') {
                    lastGpsKey = gpsKey;
                    updateCurrentGpsMarker(latitude, longitude);
                }
            } else {
                setText('liveGps', 'No GPS reading yet');
                setText('liveGpsStatus', 'Waiting for ESP32 GPS signal');
                setText('liveLocationName', 'No GPS yet');
                setText('liveLocationCoords', 'No GPS reading yet');
            }

            setText('liveLastReading', formatTelemetryDate(data.recorded_at));
            setTelemetryChip('Telemetry: Connected', 'connected');

            const readingChanged = data.recorded_at && data.recorded_at !== lastRecordedAt;
            lastRecordedAt = data.recorded_at || lastRecordedAt;

            window.dispatchEvent(new CustomEvent('coldtrace:telemetry-updated', {
                detail: data
            }));

            if (
                readingChanged
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
