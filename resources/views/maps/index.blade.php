@extends('layouts.app')

@section('title', 'Live Monitoring Map')

@section('content')

@php
    $activeTripCount = $trips->where('status', 'in_progress')->count();
    $pendingTripCount = $trips->where('status', 'pending')->count();
@endphp

<div class="ct-index monitoring-page">
<header class="ct-index-header map-page-header">
    <div>
        <small>Real-time cold-chain monitoring</small>
        <h1>Live Trucks</h1>
        <p>
            Track active and pending deliveries, live GPS coordinates, product temperature,
            RSL condition, and route paths in one operations view.
        </p>
        <p id="fleetConnection" role="status">Checking saved readings every five seconds. Trucks appear when a valid GPS reading arrives.</p>
    </div>

    <div class="ct-index-actions map-header-actions">
        <button type="button" class="ct-button ct-button-light secondary-button" onclick="showAllTrips()">
            <i class="bi bi-bounding-box-circles"></i>Show all trucks
        </button>

        <a href="{{ route('dashboard') }}" class="ct-button ct-button-dark primary-button">
            <i class="bi bi-grid-1x2-fill"></i>Dashboard
        </a>
    </div>
</header>

@if (empty($googleMapsApiKey))
    <div class="warning-box">
        Google Maps API key is missing. Please add <strong>GOOGLE_MAPS_API_KEY</strong> to your <strong>.env</strong> file.
    </div>
@endif

<div class="map-summary-grid">
    <div class="summary-card">
        <div class="summary-icon active"><i class="bi bi-truck-front-fill"></i></div>
        <div>
            <span>Active Trips</span>
            <strong id="activeTripCount">{{ $activeTripCount }}</strong>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-icon pending"><i class="bi bi-clock-fill"></i></div>
        <div>
            <span>Pending Trips</span>
            <strong id="pendingTripCount">{{ $pendingTripCount }}</strong>
        </div>
    </div>

    <div class="summary-card">
        <div class="summary-icon online"><i class="bi bi-geo-alt-fill"></i></div>
        <div>
            <span>Live GPS</span>
            <strong id="onlineTruckCount">0</strong>
        </div>
    </div>

    <div class="summary-card danger">
        <div class="summary-icon critical"><i class="bi bi-thermometer-high"></i></div>
        <div>
            <span>Temp Risk</span>
            <strong id="criticalReadingCount">0</strong>
        </div>
    </div>
</div>

<div class="map-layout">
    <aside class="trip-panel">
        <div class="panel-title-row">
            <div>
                <h2>Fleet trucks</h2>
                <p>Select a truck to view its position and delivery.</p>
            </div>

            <span id="fleetTruckCount">{{ $mapTrips->count() }}</span>
        </div>

        <div class="search-box">
            <span><i class="bi bi-search"></i></span>
            <input
                type="text"
                id="fleetSearch"
                placeholder="Search truck, product, driver..."
                oninput="filterTrips(this.value)"
            >
        </div>

        <div class="trip-list" id="tripList">
            <noscript>Enable JavaScript to see live truck locations.</noscript>
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

        <div class="map-canvas">
            <div id="map"></div>

            <div class="map-notice" id="mapNotice" hidden>
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <span id="mapNoticeText"></span>
                <button type="button" class="map-notice-close" onclick="hideMapNotice()" aria-label="Dismiss">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        </div>

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

</div>

@endsection


@push('scripts')
<script>
    let trips = @json($mapTrips ?? []);
    const fleetSnapshotUrl = @json(route('monitoring.latest'));
    const liveTelemetryByTruck = new Map();
    const truckMarkers = new Map();
    let fleetMqttClient = null;
    let fleetHasPosition = false;
    let pollingFleet = false;

    let map;
    let RouteClass;
    let AdvancedMarkerElementClass;
    let markers = [];
    let routePolylines = [];
    let routeRequestVersion = 0;
    let selectedTripId = null;

    async function initializeFleetMap() {
        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([
            google.maps.importLibrary("maps"),
            google.maps.importLibrary("marker"),
        ]);
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


    }

    function showAllTrips() {
        selectedTripId = null;
        if (!map) return;
        hideMapNotice();
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
                truckNumber: trip.truck?.id,
                type: "truck",
                title: trip.truck?.plate_number ?? "ColdTrace Truck",
                infoContent: buildTruckInfoWindow(trip),
                background: "#2563eb",
            });

            marker.addListener("click", function () {
                focusTrip(trip.id);
            });

            truckMarkers.set(trip.id, marker);
            bounds.extend(position);
            hasPosition = true;
        });

        fleetHasPosition = hasPosition;
        if (hasPosition) {
            map.fitBounds(bounds);
        } else {
            map.setCenter({ lat: 14.5995, lng: 120.9842 });
            map.setZoom(11);
        }

        document.getElementById("selectedTripTitle").innerText = "All trucks";
        document.getElementById("selectedTripDetails").innerText =
            "Showing trucks with a GPS reading from the last two minutes.";
    }

    function focusTrip(tripId) {
        const trip = trips.find(function (item) {
            return Number(item.id) === Number(tripId);
        });

        if (!trip || !map) {
            return;
        }

        hideMapNotice();
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
            truckMarkers.set(trip.id, createAdvancedMarker({
                position: truckPosition,
                truckNumber: trip.truck?.id,
                type: "truck",
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

        showMapNotice("This trip has no valid coordinates yet. A position appears once its device reports a good GPS fix.");
    }

    function showMapNotice(message) {
        const notice = document.getElementById("mapNotice");

        document.getElementById("mapNoticeText").textContent = message;
        notice.hidden = false;
    }

    function hideMapNotice() {
        document.getElementById("mapNotice").hidden = true;
    }

    /*
     * Turn a Routes API failure into something an administrator can act on.
     * The most common cause by far is the Routes API not being enabled on the
     * Google Cloud project, which reads as PERMISSION_DENIED.
     */
    function describeRouteFailure(error) {
        const detail = String(error?.message ?? error ?? "");

        if (detail.includes("PERMISSION_DENIED") || detail.includes("has not been used in project")) {
            return "Driving routes are unavailable: the Routes API is not enabled for this Google Cloud project. Enable it in the Google Cloud console, then reload. Showing a direct line instead.";
        }

        if (detail.includes("REQUEST_DENIED") || detail.includes("ApiNotActivatedMapError")) {
            return "Driving routes are unavailable: this API key is not authorised for the Routes API. Showing a direct line instead.";
        }

        if (detail.includes("OVER_QUERY_LIMIT") || detail.includes("RESOURCE_EXHAUSTED")) {
            return "The Google Routes quota for this project is used up, so driving routes are paused. Showing a direct line instead.";
        }

        return "Driving routes are unavailable right now. Showing a direct line between pickup and destination instead.";
    }

    /*
     * A straight line is not the road the truck takes, but it still shows where
     * the delivery starts and ends. Losing the whole map because one Google API
     * is switched off would be worse, so the route degrades rather than fails.
     */
    function drawDirectLine(origin, destination, message) {
        clearRoutePolylines();

        const directLine = new google.maps.Polyline({
            path: [origin, destination],
            strokeColor: "#64748b",
            strokeOpacity: 0,
            strokeWeight: 3,
            icons: [{
                icon: {
                    path: "M 0,-1 0,1",
                    strokeOpacity: 0.85,
                    strokeWeight: 3,
                    scale: 3,
                },
                offset: "0",
                repeat: "14px",
            }],
        });

        directLine.setMap(map);
        routePolylines = [directLine];

        fitMapToPath([origin, destination]);
        showMapNotice(message);
    }

    async function drawRoute(origin, destination) {
        const routeTruckId = selectedTripId;
        clearRoutePolylines();
        const requestVersion = routeRequestVersion;
        hideMapNotice();

        try {
            const request = {
                origin: origin,
                destination: destination,
                travelMode: "DRIVING",
                fields: ["path"],
            };

            RouteClass ??= (await google.maps.importLibrary("routes")).Route;
            const { routes } = await RouteClass.computeRoutes(request);
            if (selectedTripId !== routeTruckId || requestVersion !== routeRequestVersion) return;

            if (!routes || routes.length === 0) {
                drawDirectLine(
                    origin,
                    destination,
                    "Google could not find a driving route between these points. Showing a direct line instead."
                );
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
            if (selectedTripId !== routeTruckId || requestVersion !== routeRequestVersion) return;
            console.error("Routes API request failed:", error);

            drawDirectLine(origin, destination, describeRouteFailure(error));
        }
    }

    function fitMapToPath(path) {
        const bounds = new google.maps.LatLngBounds();

        path.forEach(function (point) {
            bounds.extend(point);
        });
        const selected = trips.find(trip => trip.id === selectedTripId);
        const position = selected && getBestTruckPosition(selected);
        if (position) bounds.extend(position);

        map.fitBounds(bounds);
    }

    function createAdvancedMarker(options) {
        const markerContent = options.type === "truck"
            ? window.createColdTraceTruckMarker(options.truckNumber)
            : document.createElement("div");

        if (options.type !== "truck") {
            markerContent.style.background = options.background ?? "#2563eb";
            markerContent.style.color = "white";
            markerContent.style.padding = "8px 10px";
            markerContent.style.borderRadius = "999px";
            markerContent.style.fontSize = "11px";
            markerContent.style.fontWeight = "800";
            markerContent.style.boxShadow = "0 8px 18px rgba(15, 23, 42, 0.28)";
            markerContent.style.border = "2px solid white";
            markerContent.innerText = options.label ?? "CT";
        }

        const marker = new AdvancedMarkerElementClass({
            map: map,
            position: options.position,
            content: markerContent,
            zIndex: options.type === "truck" ? 10 : 1,
            title: options.title ?? "ColdTrace Marker",
        });

        const infoWindow = new google.maps.InfoWindow({
            content: options.infoContent ?? "",
        });

        marker.fleetInfoWindow = infoWindow;
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
        if (!location || location.lat == null || location.lng == null) return null;
        const lat = Number(location.lat), lng = Number(location.lng);
        return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90
            && Math.abs(lng) <= 180 && !(lat === 0 && lng === 0) ? {lat, lng} : null;
    }

    function getBestTruckPosition(trip) {
        const time = Date.parse(trip.gps?.recorded_at);
        if (!Number.isFinite(time) || Date.now() - time > 120000 || time - Date.now() > 30000) return null;
        return getLatLng(trip.gps);
    }

    function clearMarkers() {
        truckMarkers.forEach(marker => { marker.map = null; });
        truckMarkers.clear();
        markers.forEach(function (marker) {
            marker.map = null;
        });

        markers = [];
    }

    function clearRoutePolylines() {
        routeRequestVersion++;
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
                GPS Update: ${escapeHtml(trip.gps?.recorded_at ?? "Waiting for GPS")}<br>
                Temperature Update: ${escapeHtml(trip.latestTelemetry?.recorded_at ?? "N/A")}
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

    window.initColdTraceMap = async function () {
        try { await initializeFleetMap(); }
        catch (error) { showMapNotice("The map could not load. Check your connection and reload the page."); }
    };
</script>

@if (!empty($googleMapsApiKey))
<script async src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&loading=async&callback=initColdTraceMap"></script>
@endif
@include('maps.live-feed')
@endpush
