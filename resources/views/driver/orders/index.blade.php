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
        : 'No ESP32 GPS reading yet';
@endphp

<div class="driver-orders-page">

    <div class="driver-orders-header">
        <div>
            <span class="eyebrow">Driver Orders</span>
            <h1>My Assigned Orders</h1>
            <p>
                Build one efficient route from your truck's current GPS to every active delivery.
            </p>
        </div>

        <div class="header-actions">
            <button type="button" class="primary-button" onclick="optimizeDriverOrdersRoute(true)">
                <i class="bi bi-signpost-split"></i>
                Optimize Route
            </button>
        </div>
    </div>

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
                    {{ ($hasCurrentGps ?? false) ? 'Using latest ESP32 telemetry.' : 'Use ESP32 telemetry or browser GPS before optimizing.' }}
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

            <button type="button" class="secondary-button" onclick="useBrowserLocationForRoute()">
                <i class="bi bi-crosshair"></i>
                Use My Phone GPS
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
                                    View Details
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

        <a href="#" target="_blank" rel="noopener" id="mobileOpenMapsButton" class="disabled-link">
            <i class="bi bi-map"></i>
            Maps
        </a>    </nav>
</div>

@endsection

@push('styles')
<style>
    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    .driver-orders-page {
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
        gap: 20px;
        width: 100%;
        max-width: 100%;
        color: var(--ct-slate-900);
    }

    .driver-orders-page button,
    .driver-orders-page a,
    .driver-orders-page input,
    .driver-orders-page select {
        -webkit-tap-highlight-color: transparent;
    }

    .driver-orders-header,
    .route-planner-panel,
    .missing-location-panel,
    .driver-orders-panel {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .driver-orders-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 24px;
        padding: 24px;
        border-radius: 24px;
        background:
            radial-gradient(circle at top left, rgba(34, 211, 238, 0.18), transparent 35%),
            linear-gradient(135deg, #ffffff, #f8fafc);
    }

    .eyebrow,
    .section-kicker {
        display: inline-flex;
        width: fit-content;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
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

    .warning-text {
        color: #d97706;
    }

    .driver-orders-header h1 {
        margin: 0;
        color: var(--ct-slate-900);
        font-size: clamp(28px, 4vw, 34px);
        font-weight: 950;
        letter-spacing: -0.05em;
        line-height: 1.05;
    }

    .driver-orders-header p,
    .route-planner-header p,
    .missing-location-header p,
    .panel-header p {
        margin: 8px 0 0;
        color: var(--ct-slate-500);
        line-height: 1.6;
    }

    .driver-orders-header p {
        max-width: 760px;
    }

    .header-actions,
    .route-action-row,
    .map-legend {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .header-actions {
        justify-content: flex-end;
        flex-shrink: 0;
    }

    .primary-button,
    .secondary-button,
    .filter-form button,
    .clear-button,
    .small-button,
    .mobile-driver-action-bar a,
    .mobile-driver-action-bar button {
        border: 0;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 44px;
        border-radius: 999px;
        padding: 0 18px;
        font-size: 14px;
        font-weight: 950;
        white-space: nowrap;
        transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
    }

    .primary-button,
    .filter-form button {
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        box-shadow: 0 12px 22px rgba(37, 99, 235, 0.22);
    }

    .secondary-button,
    .clear-button {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid #e2e8f0;
    }

    .primary-button:active,
    .secondary-button:active,
    .filter-form button:active,
    .clear-button:active,
    .mobile-driver-action-bar a:active,
    .mobile-driver-action-bar button:active {
        transform: scale(0.98);
    }

    .disabled-link {
        opacity: 0.55;
        pointer-events: none;
    }

    .flash-message,
    .warning-box,
    .route-message {
        padding: 14px 16px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.5;
    }

    .flash-message.success {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .flash-message.error,
    .warning-box {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .route-message {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        margin: 16px 0;
    }

    .route-message.warning {
        background: #fffbeb;
        color: #92400e;
        border-color: #fde68a;
    }

    .route-message.success {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }

    .route-summary-card span {
        display: block;
        color: var(--ct-slate-500);
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }

    .route-summary-card strong {
        display: block;
        color: var(--ct-slate-900);
        font-size: 26px;
        font-weight: 950;
        line-height: 1.1;
        overflow-wrap: anywhere;
    }

    .route-summary-card small {
        display: block;
        margin-top: 6px;
        color: var(--ct-slate-500);
        font-size: 12px;
        font-weight: 800;
        line-height: 1.45;
    }

    .route-planner-panel,
    .missing-location-panel,
    .driver-orders-panel {
        border-radius: 26px;
        padding: 22px;
    }

    .route-planner-header,
    .missing-location-header,
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 18px;
    }

    .route-planner-header h2,
    .missing-location-header h2,
    .panel-header h2 {
        margin: 0;
        color: var(--ct-slate-900);
        font-size: clamp(20px, 3vw, 24px);
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .route-status-badge,
    .missing-location-header > span,
    .route-order-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    .route-status-badge {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }

    .route-status-badge.success {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }

    .route-status-badge.warning {
        background: #fffbeb;
        color: #d97706;
        border-color: #fde68a;
    }

    .route-status-badge.error {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .route-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }

    .route-summary-card {
        min-width: 0;
        border-radius: 22px;
        border: 1px solid #e5e7eb;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 45%),
            #f8fafc;
        padding: 16px;
    }

    .current-gps-card {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .current-gps-card.waiting {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .route-action-row {
        margin-bottom: 0;
    }

    .route-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(330px, 410px);
        gap: 16px;
        align-items: stretch;
    }

    .map-card,
    .optimized-route-card {
        min-width: 0;
        border-radius: 24px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        overflow: hidden;
    }

    .map-toolbar,
    .optimized-route-header {
        padding: 16px 18px;
        border-bottom: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .map-toolbar {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: center;
    }

    .map-toolbar strong,
    .optimized-route-header strong {
        display: block;
        color: #0f172a;
        font-size: 17px;
        font-weight: 950;
    }

    .map-toolbar span,
    .optimized-route-header small,
    .optimized-route-header span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        margin-top: 4px;
        line-height: 1.4;
    }

    .optimized-route-header span {
        color: #2563eb;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 11px;
        margin-top: 0;
        margin-bottom: 5px;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        display: inline-flex;
        margin-right: 6px;
    }

    .legend-dot.truck { background: #16a34a; }
    .legend-dot.stop { background: #2563eb; }

    .map-legend span {
        color: #475569;
        font-size: 12px;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        margin: 0;
    }

    #driverOrdersRouteMap {
        width: 100%;
        height: min(68vh, 640px);
        min-height: 430px;
        background: #e2e8f0;
    }

    .optimized-route-card {
        display: flex;
        flex-direction: column;
        max-height: min(68vh, 640px);
    }

    .optimized-stop-list {
        padding: 14px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .optimized-stop-card {
        width: 100%;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        border-radius: 18px;
        padding: 14px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        text-align: left;
        cursor: pointer;
    }

    .stop-number {
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        font-size: 13px;
        font-weight: 950;
    }

    .stop-body {
        min-width: 0;
        display: block;
    }

    .stop-body strong,
    .missing-location-card strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        font-weight: 950;
        overflow-wrap: anywhere;
    }

    .stop-body em,
    .missing-location-card span {
        display: block;
        color: #2563eb;
        font-size: 12px;
        font-style: normal;
        font-weight: 900;
        margin-top: 3px;
    }

    .stop-body small,
    .missing-location-card small {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        line-height: 1.45;
        margin-top: 4px;
        overflow-wrap: anywhere;
    }

    .stop-actions-inline {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .stop-mini-action {
        min-height: 30px;
        border-radius: 999px;
        padding: 0 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
        text-decoration: none;
        font-size: 11px;
        font-weight: 950;
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

    .missing-location-header > span {
        background: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
    }

    .missing-location-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .missing-location-card {
        border-radius: 18px;
        border: 1px solid #fde68a;
        background: #fffbeb;
        padding: 14px;
        min-width: 0;
    }

    .filter-form {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 9px;
        flex-wrap: wrap;
    }

    .search-input-wrap {
        position: relative;
    }

    .search-input-wrap i {
        position: absolute;
        top: 50%;
        left: 13px;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
    }

    .search-input-wrap input,
    .filter-form select {
        min-width: 220px;
        height: 44px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 999px;
        padding: 0 15px;
        color: #0f172a;
        font-size: 14px;
        outline: none;
    }

    .search-input-wrap input {
        padding-left: 38px;
    }

    .clear-button,
    .small-button,
    .filter-form button {
        min-height: 40px;
        padding: 0 14px;
        font-size: 13px;
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .driver-table {
        width: 100%;
        border-collapse: collapse;
    }

    .driver-table th {
        text-align: left;
        padding: 14px;
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .driver-table td {
        padding: 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }

    .main-cell strong,
    .date-cell strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        font-weight: 900;
        overflow-wrap: anywhere;
    }

    .main-cell small,
    .date-cell small,
    .route-cell small {
        display: block;
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .route-cell span {
        display: block;
        color: #0f172a;
        font-size: 13px;
        font-weight: 800;
        max-width: 320px;
        overflow-wrap: anywhere;
    }

    .status-badge {
        display: inline-flex;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-pending { background: #fffbeb; color: #d97706; }
    .status-approved { background: #eff6ff; color: #2563eb; }
    .status-assigned,
    .status-in_transit { background: #ecfeff; color: #0891b2; }
    .status-delivered { background: #f0fdf4; color: #15803d; }
    .status-cancelled { background: #fef2f2; color: #dc2626; }

    .route-order-badge {
        display: none;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .route-order-badge.active {
        display: inline-flex;
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .route-order-badge.next {
        display: inline-flex;
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }

    .muted {
        color: #94a3b8;
        font-weight: 700;
    }

    .pagination-box {
        margin-top: 18px;
    }

    .empty-state {
        min-height: 180px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-align: center;
        color: #64748b;
    }

    .empty-state i {
        font-size: 34px;
        color: #2563eb;
    }

    .empty-state strong {
        color: #0f172a;
        font-size: 16px;
        font-weight: 900;
    }

    .mobile-driver-action-bar {
        display: none;
    }

    @media (max-width: 1240px) {
        .route-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .route-layout {
            grid-template-columns: 1fr;
        }

        .optimized-route-card {
            max-height: 520px;
        }

        .missing-location-list {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 980px) {
        .driver-orders-header,
        .route-planner-header,
        .missing-location-header,
        .panel-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .header-actions,
        .filter-form,
        .filter-form select,
        .search-input-wrap,
        .search-input-wrap input {
            width: 100%;
        }

        .header-actions .primary-button,
        .header-actions .secondary-button,
        .route-action-row .primary-button,
        .route-action-row .secondary-button,
        .route-action-row a,
        .filter-form button,
        .clear-button {
            flex: 1 1 180px;
        }
    }

    @media (max-width: 760px) {
        .driver-orders-page {
            padding-bottom: calc(86px + env(safe-area-inset-bottom));
        }

        .driver-orders-header,
        .route-planner-panel,
        .missing-location-panel,
        .driver-orders-panel {
            border-radius: 20px;
            padding: 16px;
        }

        .route-summary-grid,
        .missing-location-list {
            grid-template-columns: 1fr;
        }

        .route-summary-card,
        .optimized-stop-card,
        .missing-location-card {
            border-radius: 18px;
        }

        .map-toolbar {
            align-items: flex-start;
            flex-direction: column;
        }

        #driverOrdersRouteMap {
            height: 420px;
            min-height: 360px;
        }

        .optimized-route-card {
            max-height: none;
        }

        .optimized-stop-list {
            max-height: 420px;
        }

        .driver-table,
        .driver-table thead,
        .driver-table tbody,
        .driver-table th,
        .driver-table td,
        .driver-table tr {
            display: block;
        }

        .driver-table thead {
            display: none;
        }

        .driver-table tr {
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 12px;
            margin-bottom: 12px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
        }

        .driver-table td {
            border-bottom: 0;
            padding: 10px 0;
            display: grid;
            grid-template-columns: 120px minmax(0, 1fr);
            gap: 12px;
        }

        .driver-table td::before {
            content: attr(data-label);
            color: #64748b;
            font-size: 11px;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .route-cell span {
            max-width: none;
        }

        .small-button {
            width: 100%;
        }

        .mobile-driver-action-bar {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: calc(12px + env(safe-area-inset-bottom));
            z-index: 999;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
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
            box-shadow: none;
        }

        .mobile-driver-action-bar button {
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.22);
        }
    }

    @media (max-width: 480px) {
        .driver-orders-header h1 {
            font-size: 26px;
        }

        .header-actions .primary-button,
        .header-actions .secondary-button,
        .route-action-row .primary-button,
        .route-action-row .secondary-button,
        .route-action-row a {
            flex-basis: 100%;
            width: 100%;
        }

        .driver-table td {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        #driverOrdersRouteMap {
            height: 340px;
            min-height: 320px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .primary-button,
        .secondary-button,
        .filter-form button,
        .clear-button,
        .mobile-driver-action-bar a,
        .mobile-driver-action-bar button {
            transition: none;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const deliveryStops = @json($routeStops->values());
    const initialCurrentPosition = @json(($hasCurrentGps ?? false) ? ['lat' => (float) $currentLat, 'lng' => (float) $currentLng] : null);
    const googleApiKey = @json($googleMapsApiKey ?? '');

    const MAX_EXHAUSTIVE_ROUTE_STOPS = 10;
    const MAX_ROUTES_API_STOPS = 25;

    let driverOrdersRouteMap = null;
    let AdvancedMarkerElementClass = null;
    let currentTruckMarker = null;
    let stopMarkers = new Map();
    let routePolyline = null;
    let latestCurrentPosition = initialCurrentPosition;
    let optimizedStops = [...deliveryStops];
    let routeHasBeenOptimized = false;
    let lastRouteResult = null;

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
        latestCurrentPosition = {
            lat: Number(position.lat),
            lng: Number(position.lng),
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
    }

    function useBrowserLocationForRoute() {
        if (!navigator.geolocation) {
            setRouteBadge('GPS unavailable', 'error');
            setRouteMessage('Your browser does not support location access. Use ESP32 GPS telemetry instead.', 'warning');
            return;
        }

        setRouteBadge('Getting phone GPS', 'warning');
        setRouteMessage('Requesting your phone GPS location...', 'warning');

        navigator.geolocation.getCurrentPosition(
            function (position) {
                updateCurrentGpsDisplay({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                }, 'Using phone browser GPS fallback.');

                optimizeDriverOrdersRoute(true);
            },
            function (error) {
                console.error('Browser geolocation error:', error);
                setRouteBadge('GPS denied', 'error');
                setRouteMessage('Phone GPS permission was denied or unavailable. Use ESP32 GPS telemetry before optimizing.', 'warning');
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 15000,
            }
        );
    }

    async function optimizeDriverOrdersRoute(shouldFocus) {
        if (!deliveryStops.length) {
            setRouteBadge('No stops', 'warning');
            setRouteMessage('There are no active assigned orders with delivery coordinates to optimize.', 'warning');
            return;
        }

        if (!latestCurrentPosition) {
            setRouteBadge('GPS needed', 'warning');
            setRouteMessage('ColdTrace needs your current truck GPS before it can calculate the shortest delivery sequence. Wait for ESP32 GPS or tap <strong>Use My Phone GPS</strong>.', 'warning');
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
        const marker = document.createElement('div');
        marker.style.width = type === 'truck' ? '46px' : '38px';
        marker.style.height = type === 'truck' ? '46px' : '38px';
        marker.style.borderRadius = type === 'truck' ? '999px' : '14px';
        marker.style.display = 'flex';
        marker.style.alignItems = 'center';
        marker.style.justifyContent = 'center';
        marker.style.color = '#ffffff';
        marker.style.fontWeight = '950';
        marker.style.fontSize = type === 'truck' ? '13px' : '14px';
        marker.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.26)';
        marker.style.border = '3px solid #ffffff';
        marker.style.background = type === 'truck'
            ? 'linear-gradient(135deg, #16a34a, #15803d)'
            : 'linear-gradient(135deg, #2563eb, #1d4ed8)';
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
        if (!expectedDeviceCode) {
            return true;
        }

        return data.device_code === expectedDeviceCode;
    }

    function handleDriverTelemetryPayload(data) {
        if (!shouldAcceptDriverPayload(data)) {
            return;
        }

        const latitude = data.latitude ?? null;
        const longitude = data.longitude ?? null;

        if (latitude === null || longitude === null) {
            return;
        }

        updateCurrentGpsDisplay({
            lat: Number(latitude),
            lng: Number(longitude),
        }, data.gps_valid ? 'Using live ESP32 GPS telemetry.' : 'ESP32 coordinates received, GPS not marked valid.');

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
