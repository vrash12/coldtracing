@extends('layouts.app')

@section('title', 'Live Monitoring Map')

@section('content')

@php
    $activeTripCount = $trips->where('status', 'in_progress')->count();
    $pendingTripCount = $trips->where('status', 'pending')->count();

    $criticalReadingCount = $trips->filter(function ($trip) {
        $latest = $trip->latestTelemetry;
        $product = $trip->product;

        if (!$latest || !$product) {
            return false;
        }

        return $latest->temperature < $product->min_temp || $latest->temperature > $product->max_temp;
    })->count();

    $onlineTruckCount = $trips->filter(function ($trip) {
        return $trip->latestTelemetry !== null;
    })->count();
@endphp

<div class="map-page-header">
    <div>
        <span class="eyebrow">Real-time cold-chain monitoring</span>
        <h1>Live Trucks</h1>
        <p>
            Track active and pending deliveries, live GPS coordinates, product temperature,
            RSL condition, and route paths in one operations view.
        </p>
    </div>

    <div class="map-header-actions">
        <button type="button" class="secondary-button" onclick="showAllTrips()">
            Show All Trucks
        </button>

        <a href="{{ route('dashboard') }}" class="primary-button">
            Admin Dashboard
        </a>
    </div>
</div>

@if (empty($googleMapsApiKey))
    <div class="warning-box">
        Google Maps API key is missing. Please add <strong>GOOGLE_MAPS_API_KEY</strong> to your <strong>.env</strong> file.
    </div>
@endif

<div class="map-summary-grid">
    <div class="summary-card">
        <div class="summary-icon active">GO</div>
        <div>
            <span>Active Trips</span>
            <strong>{{ $activeTripCount }}</strong>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-icon pending">PN</div>
        <div>
            <span>Pending Trips</span>
            <strong>{{ $pendingTripCount }}</strong>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-icon online">GPS</div>
        <div>
            <span>With GPS Data</span>
            <strong>{{ $onlineTruckCount }}</strong>
        </div>
    </div>

    <div class="summary-card danger">
        <div class="summary-icon critical">°C</div>
        <div>
            <span>Temp Risk</span>
            <strong>{{ $criticalReadingCount }}</strong>
        </div>
    </div>
</div>

<div class="map-layout">
    <aside class="trip-panel">
        <div class="panel-title-row">
            <div>
                <h2>Active / Pending Trips</h2>
                <p>Select a delivery to focus the route.</p>
            </div>

            <span>{{ $trips->count() }}</span>
        </div>

        <div class="search-box">
            <span>⌕</span>
            <input
                type="text"
                placeholder="Search truck, product, driver..."
                oninput="filterTrips(this.value)"
            >
        </div>

        <div class="trip-list" id="tripList">
            @forelse ($trips as $trip)
                @php
                    $latest = $trip->latestTelemetry;
                    $product = $trip->product;

                    $temperatureStatus = 'No Data';
                    $temperatureClass = 'neutral';

                    if ($latest && $product) {
                        if ($latest->temperature < $product->min_temp) {
                            $temperatureStatus = 'Too Low';
                            $temperatureClass = 'warning';
                        } elseif ($latest->temperature > $product->max_temp) {
                            $temperatureStatus = 'Too High';
                            $temperatureClass = 'critical';
                        } else {
                            $temperatureStatus = 'Safe';
                            $temperatureClass = 'safe';
                        }
                    }

                    $searchText = strtolower(
                        ($trip->truck?->plate_number ?? '') . ' ' .
                        ($trip->product?->name ?? '') . ' ' .
                        ($trip->driver?->name ?? '') . ' ' .
                        ($trip->receiver?->name ?? '') . ' ' .
                        ($trip->status ?? '')
                    );
                @endphp

                <button
                    type="button"
                    class="trip-button"
                    id="trip-button-{{ $trip->id }}"
                    data-search="{{ $searchText }}"
                    onclick="focusTrip({{ $trip->id }})"
                >
                    <div class="trip-top">
                        <div>
                            <strong>{{ $trip->truck?->plate_number ?? 'No Truck' }}</strong>
                            <span>{{ $trip->product?->name ?? 'No Product' }}</span>
                        </div>

                        <em class="status-badge status-{{ $trip->status }}">
                            {{ ucfirst(str_replace('_', ' ', $trip->status)) }}
                        </em>
                    </div>

                    <div class="trip-meta">
                        <small>Driver: {{ $trip->driver?->name ?? 'No Driver' }}</small>
                        <small>Receiver: {{ $trip->receiver?->name ?? 'No Receiver' }}</small>
                    </div>

                    <div class="trip-bottom">
                        @if ($latest)
                            <span class="temperature-pill {{ $temperatureClass }}">
                                {{ $latest->temperature }} °C
                                <small>{{ $temperatureStatus }}</small>
                            </span>

                            <span class="rsl-pill">
                                RSL: {{ $latest->rsl_hours ?? 'N/A' }} hrs
                            </span>
                        @else
                            <span class="temperature-pill neutral">
                                No GPS Data
                            </span>
                        @endif
                    </div>
                </button>
            @empty
                <div class="empty-box">
                    <strong>No trips available.</strong>
                    <span>Active and pending trips will appear here once created.</span>
                </div>
            @endforelse
        </div>
    </aside>

    <section class="map-card">
        <div class="map-toolbar">
            <div>
                <strong>ColdTrace Live Map</strong>
                <span>Origin • Truck • Destination • Route Path</span>
            </div>

            <div class="legend">
                <span><i class="legend-dot origin"></i> Origin</span>
                <span><i class="legend-dot truck"></i> Truck</span>
                <span><i class="legend-dot destination"></i> Destination</span>
            </div>
        </div>

        <div id="map"></div>

        <div class="map-info">
            <div class="map-info-icon">CT</div>

            <div>
                <strong id="selectedTripTitle">Select a trip</strong>
                <span id="selectedTripDetails">
                    Click a trip on the left to view truck position and route.
                </span>
            </div>
        </div>
    </section>
</div>

@endsection

@push('styles')
<style>
    .map-page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 20px;
        padding: 24px;
        border-radius: 24px;
        background:
            radial-gradient(circle at top left, rgba(34, 211, 238, 0.18), transparent 35%),
            linear-gradient(135deg, #ffffff, #f8fafc);
        border: 1px solid #e5e7eb;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .eyebrow {
        display: inline-flex;
        background: #ecfeff;
        color: #0891b2;
        border: 1px solid #cffafe;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 12px;
    }

    .map-page-header h1 {
        margin: 0;
        color: #0f172a;
        font-size: 34px;
        font-weight: 800;
        letter-spacing: -0.9px;
    }

    .map-page-header p {
        margin: 8px 0 0;
        color: #64748b;
        max-width: 680px;
        line-height: 1.6;
    }

    .map-header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .warning-box {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
        padding: 14px 16px;
        border-radius: 14px;
        margin-bottom: 20px;
        box-shadow: 0 8px 18px rgba(245, 158, 11, 0.08);
    }

    .map-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .summary-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
    }

    .summary-icon {
        width: 44px;
        height: 44px;
        border-radius: 15px;
        color: white;
        font-size: 12px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .summary-icon.active {
        background: linear-gradient(135deg, #16a34a, #15803d);
    }

    .summary-icon.pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .summary-icon.online {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
    }

    .summary-icon.critical {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }

    .summary-card span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .summary-card strong {
        display: block;
        color: #0f172a;
        font-size: 28px;
        font-weight: 800;
        line-height: 1;
    }

    .summary-card.danger strong {
        color: #dc2626;
    }

    .map-layout {
        display: grid;
        grid-template-columns: 380px minmax(0, 1fr);
        gap: 20px;
        align-items: stretch;
    }

    .trip-panel {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        padding: 18px;
        height: 720px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .panel-title-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 14px;
    }

    .panel-title-row h2 {
        margin: 0;
        color: #0f172a;
        font-size: 18px;
        font-weight: 800;
    }

    .panel-title-row p {
        margin: 5px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .panel-title-row > span {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 800;
    }

    .search-box {
        position: relative;
        margin-bottom: 14px;
    }

    .search-box span {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-weight: 800;
    }

    .search-box input {
        width: 100%;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 14px;
        padding: 12px 14px 12px 36px;
        outline: none;
        color: #0f172a;
        font-size: 13px;
        transition: 0.2s ease;
    }

    .search-box input:focus {
        border-color: #2563eb;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .trip-list {
        overflow-y: auto;
        padding-right: 4px;
    }

    .trip-button {
        width: 100%;
        text-align: left;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 15px;
        margin-bottom: 12px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 12px;
        transition: 0.2s ease;
    }

    .trip-button:hover {
        background: #eff6ff;
        border-color: #93c5fd;
        transform: translateY(-1px);
    }

    .trip-button.active-trip {
        background: #eff6ff;
        border-color: #2563eb;
        box-shadow: 0 12px 24px rgba(37, 99, 235, 0.13);
    }

    .trip-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
    }

    .trip-top strong {
        display: block;
        color: #0f172a;
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .trip-top span {
        display: block;
        color: #2563eb;
        font-size: 13px;
        font-weight: 800;
    }

    .status-badge {
        border-radius: 999px;
        padding: 5px 9px;
        font-size: 10px;
        font-style: normal;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-pending {
        background: #fffbeb;
        color: #d97706;
    }

    .status-in_progress {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-completed {
        background: #f0fdf4;
        color: #16a34a;
    }

    .status-cancelled {
        background: #fef2f2;
        color: #dc2626;
    }

    .trip-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .trip-meta small {
        color: #64748b;
        font-size: 12px;
    }

    .trip-bottom {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .temperature-pill,
    .rsl-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }

    .temperature-pill small {
        font-size: 10px;
        opacity: 0.85;
    }

    .temperature-pill.safe {
        background: #f0fdf4;
        color: #16a34a;
    }

    .temperature-pill.warning {
        background: #fffbeb;
        color: #d97706;
    }

    .temperature-pill.critical {
        background: #fef2f2;
        color: #dc2626;
    }

    .temperature-pill.neutral {
        background: #f1f5f9;
        color: #64748b;
    }

    .rsl-pill {
        background: #eff6ff;
        color: #2563eb;
    }

    .map-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        overflow: hidden;
        position: relative;
        min-width: 0;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .map-toolbar {
        position: absolute;
        z-index: 6;
        top: 16px;
        left: 16px;
        right: 16px;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 18px;
        padding: 13px 15px;
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: center;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.12);
    }

    .map-toolbar strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
    }

    .map-toolbar span {
        color: #64748b;
        font-size: 12px;
    }

    .legend {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .legend span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        display: inline-block;
    }

    .legend-dot.origin {
        background: #16a34a;
    }

    .legend-dot.truck {
        background: #2563eb;
    }

    .legend-dot.destination {
        background: #dc2626;
    }

    #map {
        width: 100%;
        height: 720px;
    }

    .map-info {
        position: absolute;
        left: 20px;
        bottom: 20px;
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(226, 232, 240, 0.95);
        backdrop-filter: blur(12px);
        border-radius: 18px;
        padding: 15px 16px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
        display: grid;
        grid-template-columns: 42px 1fr;
        align-items: center;
        gap: 12px;
        max-width: 560px;
        z-index: 5;
    }

    .map-info-icon {
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 12px;
    }

    .map-info strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .map-info span {
        display: block;
        color: #475569;
        font-size: 13px;
        line-height: 1.5;
    }

    .empty-box {
        padding: 18px;
        background: #f8fafc;
        color: #64748b;
        border-radius: 16px;
        font-size: 14px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        text-align: center;
    }

    .empty-box strong {
        color: #0f172a;
    }

    @media (max-width: 1180px) {
        .map-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .map-layout {
            grid-template-columns: 1fr;
        }

        .trip-panel {
            height: auto;
            max-height: 450px;
        }

        #map {
            height: 620px;
        }
    }

    @media (max-width: 760px) {
        .map-page-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 20px;
        }

        .map-page-header h1 {
            font-size: 28px;
        }

        .map-header-actions {
            width: 100%;
        }

        .map-header-actions a,
        .map-header-actions button {
            width: 100%;
            text-align: center;
        }

        .map-summary-grid {
            grid-template-columns: 1fr;
        }

        .map-toolbar {
            flex-direction: column;
            align-items: flex-start;
        }

        .legend {
            justify-content: flex-start;
        }

        #map {
            height: 540px;
        }

        .map-info {
            left: 12px;
            right: 12px;
            bottom: 12px;
            max-width: none;
            grid-template-columns: 1fr;
        }

        .map-info-icon {
            display: none;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const trips = @json($mapTrips ?? []);

    let map;
    let RouteClass;
    let AdvancedMarkerElementClass;
    let markers = [];
    let routePolylines = [];
    let selectedTripId = null;

    async function initColdTraceMap() {
        const [{ Map }, { Route }, { AdvancedMarkerElement }] = await Promise.all([
            google.maps.importLibrary("maps"),
            google.maps.importLibrary("routes"),
            google.maps.importLibrary("marker"),
        ]);

        RouteClass = Route;
        AdvancedMarkerElementClass = AdvancedMarkerElement;

     map = new Map(document.getElementById("map"), {
    center: { lat: 14.5995, lng: 120.9842 },
    zoom: 11,

    /*
    |--------------------------------------------------------------------------
    | Map Scroll Behavior
    |--------------------------------------------------------------------------
    | This removes the "Hold Ctrl to zoom" behavior.
    | Now the user can zoom the map using normal mouse scroll.
    */
    gestureHandling: "greedy",
    scrollwheel: true,

    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: true,
    mapId: "DEMO_MAP_ID",
});

        showAllTrips();

        if (trips.length > 0) {
            focusTrip(trips[0].id);
        }
    }

    function showAllTrips() {
        selectedTripId = null;
        clearMarkers();
        clearRoutePolylines();
        clearActiveTripButtons();

        const bounds = new google.maps.LatLngBounds();
        let hasPosition = false;

        trips.forEach(function (trip) {
            const position = getBestTruckPosition(trip);

            if (!position) {
                return;
            }

            const marker = createAdvancedMarker({
                position: position,
                label: "TRK",
                title: trip.truck?.plate_number ?? "ColdTrace Truck",
                infoContent: buildTruckInfoWindow(trip),
                background: "#2563eb",
            });

            marker.addListener("click", function () {
                focusTrip(trip.id);
            });

            markers.push(marker);
            bounds.extend(position);
            hasPosition = true;
        });

        if (hasPosition) {
            map.fitBounds(bounds);
        } else {
            map.setCenter({ lat: 14.5995, lng: 120.9842 });
            map.setZoom(11);
        }

        document.getElementById("selectedTripTitle").innerText = "All trucks";
        document.getElementById("selectedTripDetails").innerText =
            "Showing all trucks with available GPS or origin coordinates.";
    }

    function focusTrip(tripId) {
        const trip = trips.find(function (item) {
            return Number(item.id) === Number(tripId);
        });

        if (!trip) {
            return;
        }

        selectedTripId = tripId;
        clearMarkers();
        clearRoutePolylines();
        setActiveTripButton(tripId);

        const origin = getLatLng(trip.origin);
        const destination = getLatLng(trip.destination);
        const truckPosition = getBestTruckPosition(trip);

        document.getElementById("selectedTripTitle").innerText =
            `${trip.truck?.plate_number ?? "No Truck"} - ${trip.product?.name ?? "No Product"}`;

        document.getElementById("selectedTripDetails").innerText =
            buildTripDetailsText(trip);

        if (origin) {
            markers.push(createAdvancedMarker({
                position: origin,
                label: "OR",
                title: "Origin",
                infoContent: `<strong>Origin</strong><br>${escapeHtml(trip.origin?.address ?? "N/A")}`,
                background: "#16a34a",
            }));
        }

        if (destination) {
            markers.push(createAdvancedMarker({
                position: destination,
                label: "DST",
                title: "Destination",
                infoContent: `<strong>Destination</strong><br>${escapeHtml(trip.destination?.address ?? "N/A")}`,
                background: "#dc2626",
            }));
        }

        if (truckPosition) {
            markers.push(createAdvancedMarker({
                position: truckPosition,
                label: "TRK",
                title: trip.truck?.plate_number ?? "ColdTrace Truck",
                infoContent: buildTruckInfoWindow(trip),
                background: "#2563eb",
            }));
        }

        if (origin && destination) {
            drawRoute(origin, destination);
            return;
        }

        if (truckPosition) {
            map.setCenter(truckPosition);
            map.setZoom(14);
            return;
        }

        alert("This trip has no valid coordinates yet.");
    }

    async function drawRoute(origin, destination) {
        clearRoutePolylines();

        try {
            const request = {
                origin: origin,
                destination: destination,
                travelMode: "DRIVING",
                fields: ["path"],
            };

            const { routes } = await RouteClass.computeRoutes(request);

            if (!routes || routes.length === 0) {
                alert("No route found for this trip.");
                return;
            }

            const selectedRoute = routes[0];

            routePolylines = selectedRoute.createPolylines();

            routePolylines.forEach(function (polyline) {
                polyline.setOptions({
                    strokeColor: "#2563eb",
                    strokeOpacity: 0.9,
                    strokeWeight: 6,
                });

                polyline.setMap(map);
            });

            if (selectedRoute.path) {
                fitMapToPath(selectedRoute.path);
            }
        } catch (error) {
            console.error("Routes API request failed:", error);

            alert(
                "Unable to load route. Make sure Maps JavaScript API and Routes API are enabled for your Google Cloud project."
            );
        }
    }

    async function fitMapToPath(path) {
        const { LatLngBounds } = await google.maps.importLibrary("core");
        const bounds = new LatLngBounds();

        path.forEach(function (point) {
            bounds.extend(point);
        });

        map.fitBounds(bounds);
    }

    function createAdvancedMarker(options) {
        const markerContent = document.createElement("div");

        markerContent.style.background = options.background ?? "#2563eb";
        markerContent.style.color = "white";
        markerContent.style.padding = "8px 10px";
        markerContent.style.borderRadius = "999px";
        markerContent.style.fontSize = "11px";
        markerContent.style.fontWeight = "800";
        markerContent.style.boxShadow = "0 8px 18px rgba(15, 23, 42, 0.28)";
        markerContent.style.border = "2px solid white";
        markerContent.innerText = options.label ?? "CT";

        const marker = new AdvancedMarkerElementClass({
            map: map,
            position: options.position,
            content: markerContent,
            title: options.title ?? "ColdTrace Marker",
        });

        const infoWindow = new google.maps.InfoWindow({
            content: options.infoContent ?? "",
        });

        marker.addListener("click", function () {
            infoWindow.open({
                anchor: marker,
                map: map,
            });
        });

        return marker;
    }

    function filterTrips(keyword) {
        const query = String(keyword).toLowerCase().trim();
        const tripButtons = document.querySelectorAll(".trip-button");

        tripButtons.forEach(function (button) {
            const searchText = button.dataset.search || "";

            if (searchText.includes(query)) {
                button.style.display = "flex";
            } else {
                button.style.display = "none";
            }
        });
    }

    function getLatLng(location) {
        if (!location || location.lat === null || location.lng === null) {
            return null;
        }

        return {
            lat: Number(location.lat),
            lng: Number(location.lng),
        };
    }

    function getBestTruckPosition(trip) {
        if (
            trip.latestTelemetry &&
            trip.latestTelemetry.lat !== null &&
            trip.latestTelemetry.lng !== null
        ) {
            return {
                lat: Number(trip.latestTelemetry.lat),
                lng: Number(trip.latestTelemetry.lng),
            };
        }

        return getLatLng(trip.origin);
    }

    function clearMarkers() {
        markers.forEach(function (marker) {
            marker.map = null;
        });

        markers = [];
    }

    function clearRoutePolylines() {
        routePolylines.forEach(function (polyline) {
            polyline.setMap(null);
        });

        routePolylines = [];
    }

    function clearActiveTripButtons() {
        document.querySelectorAll(".trip-button").forEach(function (button) {
            button.classList.remove("active-trip");
        });
    }

    function setActiveTripButton(tripId) {
        clearActiveTripButtons();

        const button = document.getElementById(`trip-button-${tripId}`);

        if (button) {
            button.classList.add("active-trip");
        }
    }

    function buildTripDetailsText(trip) {
        const driver = trip.driver?.name ?? "N/A";
        const receiver = trip.receiver?.name ?? "N/A";
        const temp = trip.latestTelemetry?.temperature ?? "No reading";
        const rsl = trip.latestTelemetry?.rsl_hours ?? "N/A";
        const status = trip.status ? trip.status.replace("_", " ") : "N/A";

        return `Driver: ${driver} | Receiver: ${receiver} | Temp: ${temp} °C | RSL: ${rsl} hours | Status: ${status}`;
    }

    function buildTruckInfoWindow(trip) {
        return `
            <div style="min-width: 240px; line-height: 1.55;">
                <strong>${escapeHtml(trip.truck?.plate_number ?? "No Truck")}</strong><br>
                Product: ${escapeHtml(trip.product?.name ?? "N/A")}<br>
                Driver: ${escapeHtml(trip.driver?.name ?? "N/A")}<br>
                Receiver: ${escapeHtml(trip.receiver?.name ?? "N/A")}<br>
                Temperature: ${escapeHtml(String(trip.latestTelemetry?.temperature ?? "No reading"))} °C<br>
                RSL: ${escapeHtml(String(trip.latestTelemetry?.rsl_hours ?? "N/A"))} hours<br>
                Last Update: ${escapeHtml(trip.latestTelemetry?.recorded_at ?? "N/A")}
            </div>
        `;
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    window.initColdTraceMap = initColdTraceMap;
</script>

<script
    async
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&loading=async&callback=initColdTraceMap">
</script>
@endpush
