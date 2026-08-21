@extends('layouts.app')

@section('title', 'Driver Dashboard')

@section('content')
@php
    $assignedTruck = $driver->assignedTruck;
@endphp

<div class="ct-dashboard">
    <section class="ct-hero">
        <div class="ct-hero-copy">
            <span class="ct-eyebrow"><span class="ct-eyebrow-dot"></span>Driver workspace</span>
            <h1>Your next delivery is the priority.</h1>
            <p>Start assigned trips, monitor the cargo, and open navigation without sorting through repeated history and status panels.</p>
            <div class="ct-hero-meta">
                <span><i class="bi bi-person-circle"></i>{{ $driver->name }}</span>
                <span><i class="bi bi-truck-front-fill"></i>{{ $assignedTruck?->plate_number ?? 'No truck assigned' }}</span>
                <span><i class="bi bi-broadcast-pin"></i>Last cargo update: {{ $latestTelemetryAt?->diffForHumans() ?? 'No readings yet' }}</span>
            </div>
        </div>
        <div class="ct-actions">
            <a href="{{ route('driver.orders.index') }}" class="ct-button ct-button-primary"><i class="bi bi-map-fill"></i>Plan my route</a>
        </div>
    </section>

    <section class="ct-metrics" aria-label="Driver work summary">
        <x-dashboard.metric label="Ready to start" :value="$pendingTrips" detail="Trips awaiting departure" icon="bi-play-circle-fill" tone="amber" :href="route('driver.orders.index', ['status' => 'assigned'])" />
        <x-dashboard.metric label="Active trips" :value="$activeTrips" detail="Deliveries currently underway" icon="bi-truck-front-fill" tone="cyan" :href="route('driver.orders.index', ['status' => 'in_transit'])" />
        <x-dashboard.metric label="Assigned orders" :value="$assignedOrders" detail="Current delivery workload" icon="bi-box-seam-fill" tone="blue" :href="route('driver.orders.index')" />
        <x-dashboard.metric label="Unread updates" :value="$unreadNotificationCount" detail="New assignment notifications" icon="bi-bell-fill" :tone="$unreadNotificationCount > 0 ? 'violet' : 'green'" />
    </section>

    <div class="ct-grid-main">
        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon cyan"><i class="bi bi-signpost-2-fill"></i></span>
                    <div class="ct-panel-title"><small>Work queue</small><h2>Current deliveries</h2></div>
                </div>
                <a href="{{ route('driver.orders.index') }}" class="ct-panel-link">All assigned orders <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="ct-panel-body">
                <div class="ct-delivery-list">
                    @forelse ($currentTrips as $trip)
                        @php
                            $reading = $trip->latestTelemetry;
                            $temperature = $reading?->temperature !== null ? (float) $reading->temperature : null;
                            $minimum = $trip->product?->min_temp !== null ? (float) $trip->product->min_temp : null;
                            $maximum = $trip->product?->max_temp !== null ? (float) $trip->product->max_temp : null;

                            if ($temperature === null || $minimum === null || $maximum === null) {
                                $conditionClass = 'neutral';
                                $conditionLabel = 'No current reading';
                                $conditionIcon = 'bi-dash-circle';
                            } elseif ($temperature > $maximum) {
                                $conditionClass = 'danger';
                                $conditionLabel = number_format($temperature, 1) . ' °C · Too high';
                                $conditionIcon = 'bi-thermometer-high';
                            } elseif ($temperature < $minimum) {
                                $conditionClass = 'warning';
                                $conditionLabel = number_format($temperature, 1) . ' °C · Too low';
                                $conditionIcon = 'bi-thermometer-low';
                            } else {
                                $conditionClass = 'safe';
                                $conditionLabel = number_format($temperature, 1) . ' °C · Safe';
                                $conditionIcon = 'bi-shield-check';
                            }

                            $destination = $trip->destination_lat !== null && $trip->destination_lng !== null
                                ? $trip->destination_lat . ',' . $trip->destination_lng
                                : $trip->destination_address;
                            $navigationUrl = $destination
                                ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($destination) . '&travelmode=driving'
                                : null;
                        @endphp
                        <article class="ct-delivery">
                            <div class="ct-delivery-top">
                                <div class="ct-delivery-title">
                                    <strong>{{ $trip->order?->order_code ?? 'Trip #' . $trip->id }}</strong>
                                    <span><i class="bi bi-geo-alt-fill"></i> {{ $trip->destination_address ?? 'Destination not set' }}</span>
                                </div>
                                <x-dashboard.status-badge :status="$trip->status" />
                            </div>
                            <div class="ct-delivery-details">
                                <div class="ct-detail"><span>Receiver</span><strong>{{ $trip->receiver?->name ?? 'No receiver' }}{{ $trip->receiver?->phone ? ' · ' . $trip->receiver->phone : '' }}</strong></div>
                                <div class="ct-detail"><span>Cargo</span><strong>{{ $trip->product?->name ?? 'Product not available' }}</strong></div>
                                <div class="ct-detail"><span>Condition</span><strong class="ct-condition ct-condition-{{ $conditionClass }}"><i class="bi {{ $conditionIcon }}"></i>{{ $conditionLabel }}</strong></div>
                            </div>
                            <div class="ct-delivery-actions">
                                @if ($trip->order)
                                    <a href="{{ route('driver.orders.show', $trip->order) }}" class="ct-button ct-button-light ct-button-small"><i class="bi bi-eye-fill"></i>Open delivery</a>
                                @endif
                                @if ($navigationUrl)
                                    <a href="{{ $navigationUrl }}" target="_blank" rel="noopener" class="ct-button ct-button-dark ct-button-small"><i class="bi bi-navigation-fill"></i>Navigate</a>
                                @endif
                                @if ($trip->status === 'pending')
                                    <form method="POST" action="{{ route('driver.trips.start', $trip) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="return_to" value="dashboard">
                                        <button type="submit" class="ct-button ct-button-success ct-button-small"><i class="bi bi-play-fill"></i>Start trip</button>
                                    </form>
                                @elseif ($trip->status === 'in_progress')
                                    <form method="POST" action="{{ route('driver.trips.complete', $trip) }}" onsubmit="return confirm('Mark this delivery as completed?');">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="return_to" value="dashboard">
                                        <button type="submit" class="ct-button ct-button-success ct-button-small"><i class="bi bi-check2-circle"></i>Complete trip</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <x-dashboard.empty-state icon="bi-check2-circle" title="No current deliveries" message="New assignments will appear here when an administrator assigns an order to you." />
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="ct-stack">
            <section class="ct-panel">
                <header class="ct-panel-header">
                    <div class="ct-panel-heading"><span class="ct-panel-icon cyan"><i class="bi bi-truck-front-fill"></i></span><div class="ct-panel-title"><small>Assigned equipment</small><h2>Vehicle & sensors</h2></div></div>
                </header>
                <div class="ct-panel-body">
                    @if ($assignedTruck)
                        <div class="ct-vehicle">
                            <div class="ct-vehicle-title"><span><i class="bi bi-truck-front-fill"></i></span><div><strong>{{ $assignedTruck->plate_number }}</strong><small>{{ $assignedTruck->model ?? 'Vehicle model not set' }} · {{ ucfirst(str_replace('_', ' ', $assignedTruck->status)) }}</small></div></div>
                            <div class="ct-vehicle-meta">
                                @forelse ($assignedTruck->devices as $device)
                                    @php $isReporting = $device->last_seen_at?->gte(now()->subMinutes(15)) ?? false; @endphp
                                    <div class="ct-detail">
                                        <span>{{ $device->device_code }}</span>
                                        <strong class="ct-condition ct-condition-{{ $isReporting ? 'safe' : 'neutral' }}"><i class="bi bi-circle-fill"></i>{{ $isReporting ? 'Reporting now' : 'Last seen ' . ($device->last_seen_at?->diffForHumans() ?? 'never') }}</strong>
                                    </div>
                                @empty
                                    <div class="ct-detail"><span>Sensor</span><strong>No device assigned</strong></div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <x-dashboard.empty-state icon="bi-truck" title="No truck assigned" message="Contact an administrator before starting a delivery." />
                    @endif
                </div>
            </section>

            @if ($openAlerts->isNotEmpty())
                <section class="ct-panel">
                    <header class="ct-panel-header">
                        <div class="ct-panel-heading"><span class="ct-panel-icon red"><i class="bi bi-exclamation-triangle-fill"></i></span><div class="ct-panel-title"><small>Act now</small><h2>Open exceptions</h2></div></div>
                    </header>
                    <div class="ct-panel-body"><div class="ct-list">
                        @foreach ($openAlerts as $alert)
                            <article class="ct-alert"><strong><span>{{ ucfirst(str_replace('_', ' ', $alert->type)) }}</span><span>{{ ucfirst($alert->severity) }}</span></strong><p>{{ $alert->message }}</p></article>
                        @endforeach
                    </div></div>
                </section>
            @endif

            <section class="ct-panel">
                <header class="ct-panel-header">
                    <div class="ct-panel-heading"><span class="ct-panel-icon"><i class="bi bi-bell-fill"></i></span><div class="ct-panel-title"><small>Assignments</small><h2>Unread updates</h2></div></div>
                    @if ($unreadNotificationCount > 0)
                        <form method="POST" action="{{ route('driver.notifications.readAll') }}">@csrf @method('PATCH')<button class="ct-panel-link" type="submit">Mark all read</button></form>
                    @endif
                </header>
                <div class="ct-panel-body"><div class="ct-list">
                    @forelse ($unreadOrderNotifications as $notification)
                        @php $notice = $notification->data ?? []; @endphp
                        <div class="ct-row-card">
                            <span class="ct-row-icon"><i class="bi bi-bell-fill"></i></span>
                            <span class="ct-row-main"><strong>{{ $notice['title'] ?? 'New assignment' }}</strong><span>{{ $notice['message'] ?? 'An order was assigned to you.' }}</span><small>{{ $notification->created_at?->diffForHumans() }}</small></span>
                            <form method="POST" action="{{ route('driver.notifications.read', $notification->id) }}">@csrf @method('PATCH')<button type="submit" class="ct-button ct-button-light ct-button-small" aria-label="Mark notification read"><i class="bi bi-check2"></i></button></form>
                        </div>
                    @empty
                        <x-dashboard.empty-state icon="bi-bell-slash" title="You are up to date" message="New assignment notifications will appear here." />
                    @endforelse
                </div></div>
            </section>
        </aside>
    </div>
</div>
@endsection

@include('dashboard.partials.role-dashboard-styles')
