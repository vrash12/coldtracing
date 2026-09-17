@extends('layouts.app')

@section('title', 'Operations Dashboard')

@section('content')
<div class="ct-dashboard">
    <section class="ct-hero">
        <div class="ct-hero-copy">
            <span class="ct-eyebrow"><span class="ct-eyebrow-dot"></span>Operations overview</span>
            <h1>Focus on deliveries that need a decision.</h1>
            <p>Review dispatch workload, live fleet activity, and cold-chain exceptions from one concise operational view.</p>
            <div class="ct-hero-meta">
                <span><i class="bi bi-box-seam-fill"></i>{{ $activeOrders }} active order{{ $activeOrders === 1 ? '' : 's' }}</span>
                <span><i class="bi bi-check2-circle"></i>{{ $deliveredToday }} delivered today</span>
                <span><i class="bi bi-broadcast-pin"></i>Last sensor update: {{ $latestTelemetryAt?->diffForHumans() ?? 'No readings yet' }}</span>
            </div>
        </div>
        <div class="ct-actions">
            <a href="{{ route('orders.index') }}" class="ct-button ct-button-primary"><i class="bi bi-bag-check-fill"></i>Manage orders</a>
            <a href="{{ route('monitoring.index') }}" class="ct-button ct-button-secondary"><i class="bi bi-map-fill"></i>Live map</a>
        </div>
    </section>

    @php
        $remainingSetup = collect($setupSteps)->reject(fn ($step) => $step['done']);
    @endphp

    @if ($remainingSetup->isNotEmpty())
        <section class="ct-panel ct-setup" aria-labelledby="setupHeading">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon amber"><i class="bi bi-clipboard-check-fill"></i></span>
                    <div class="ct-panel-title">
                        <small>{{ count($setupSteps) - $remainingSetup->count() }} of {{ count($setupSteps) }} done</small>
                        <h2 id="setupHeading">Finish setting up ColdTrace</h2>
                    </div>
                </div>
                <span class="ct-result-count">Orders cannot be dispatched until these are complete.</span>
            </header>

            <div class="ct-setup-steps">
                @foreach ($setupSteps as $step)
                    <div class="ct-setup-step {{ $step['done'] ? 'done' : 'todo' }}">
                        <span class="ct-setup-mark">
                            @if ($step['done'])
                                <i class="bi bi-check-lg"></i>
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <div class="ct-setup-copy">
                            <strong>{{ $step['label'] }}</strong>
                            <span>{{ $step['detail'] }}</span>
                        </div>
                        @if (! $step['done'] && $step['url'])
                            <a href="{{ $step['url'] }}" class="ct-button ct-button-dark ct-button-small">
                                {{ $step['action'] }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="ct-metrics" aria-label="Operational summary">
        <x-dashboard.metric label="Awaiting assignment" :value="$pendingOrders" detail="Orders without an active dispatch" icon="bi-hourglass-split" tone="amber" :href="route('orders.index', ['status' => 'pending'])" />
        <x-dashboard.metric label="In transit" :value="$inTransitOrders" detail="Deliveries currently moving" icon="bi-truck-front-fill" tone="cyan" :href="route('orders.index', ['status' => 'in_transit'])" />
        <x-dashboard.metric label="Devices reporting" :value="$reportingDevices" :detail="$assignedDevices . ' assigned devices'" icon="bi-router-fill" tone="green" :href="route('monitoring.index')" />
        <x-dashboard.metric label="Open exceptions" :value="$unresolvedAlerts" :detail="$criticalAlerts . ' critical'" icon="bi-exclamation-triangle-fill" :tone="$criticalAlerts > 0 ? 'red' : 'violet'" :href="route('monitoring.index')" />
    </section>

    <div class="ct-grid-main">
        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon cyan"><i class="bi bi-signpost-split-fill"></i></span>
                    <div class="ct-panel-title"><small>Live operations</small><h2>Active deliveries</h2></div>
                </div>
                <a href="{{ route('monitoring.index') }}" class="ct-panel-link">View map <i class="bi bi-arrow-right"></i></a>
            </header>
            <div class="ct-panel-body">
                <div class="ct-delivery-list">
                    @forelse ($activeDeliveryTrips as $trip)
                        @php
                            $reading = $trip->latestTelemetry;
                            $temperatureState = $trip->temperature_state;
                            $temperature = $temperatureState['temperature'];
                            $conditionClass = $temperatureState['class'] === 'critical'
                                ? 'danger'
                                : $temperatureState['class'];
                            $conditionLabel = $temperature === null
                                ? 'Waiting for sensor data'
                                : number_format($temperature, 1) . ' °C · ' . $temperatureState['label'];
                            $conditionIcon = $temperatureState['icon'];
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
                                <div class="ct-detail"><span>Driver & truck</span><strong>{{ $trip->driver?->name ?? 'Unassigned' }} · {{ $trip->truck?->plate_number ?? 'No truck' }}</strong></div>
                                <div class="ct-detail"><span>Cargo</span><strong>{{ $trip->product?->name ?? 'No product' }}</strong></div>
                                <div class="ct-detail"><span>Latest condition</span><strong class="ct-condition ct-condition-{{ $conditionClass }}"><i class="bi {{ $conditionIcon }}"></i>{{ $conditionLabel }}</strong></div>
                            </div>
                            <div class="ct-delivery-actions">
                                @if ($trip->order)
                                    <a href="{{ route('orders.show', $trip->order) }}" class="ct-button ct-button-light ct-button-small"><i class="bi bi-eye-fill"></i>Open order</a>
                                @endif
                                @if ($trip->latestTelemetry?->recorded_at)
                                    <span class="ct-button ct-button-small ct-static-chip"><i class="bi bi-clock"></i>{{ $trip->latestTelemetry->recorded_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <x-dashboard.empty-state icon="bi-truck" title="No active deliveries" message="Assigned and in-transit deliveries will appear here." />
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="ct-stack">
            <section class="ct-panel">
                <header class="ct-panel-header">
                    <div class="ct-panel-heading">
                        <span class="ct-panel-icon amber"><i class="bi bi-exclamation-diamond-fill"></i></span>
                        <div class="ct-panel-title"><small>Action queue</small><h2>Needs attention</h2></div>
                    </div>
                </header>
                <div class="ct-panel-body">
                    <div class="ct-attention">
                        <a href="{{ route('orders.index', ['status' => 'pending']) }}" class="ct-attention-item">
                            <i class="bi bi-person-plus-fill"></i><span class="ct-attention-copy"><strong>Assign pending orders</strong><span>Connect each request to a driver and truck.</span></span><span class="ct-attention-count">{{ $pendingOrders }}</span>
                        </a>
                        <a href="{{ route('monitoring.index') }}" class="ct-attention-item {{ $devicesNeedingAttention > 0 ? 'danger' : '' }}">
                            <i class="bi bi-router-fill"></i><span class="ct-attention-copy"><strong>Check fleet connectivity</strong><span>Assigned devices without a reading in 15 minutes.</span></span><span class="ct-attention-count">{{ $devicesNeedingAttention }}</span>
                        </a>
                        <a href="{{ route('monitoring.index') }}" class="ct-attention-item {{ $criticalAlerts > 0 ? 'danger' : '' }}">
                            <i class="bi bi-thermometer-high"></i><span class="ct-attention-copy"><strong>Review critical exceptions</strong><span>Unresolved cold-chain events marked critical.</span></span><span class="ct-attention-count">{{ $criticalAlerts }}</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="ct-panel">
                <header class="ct-panel-header">
                    <div class="ct-panel-heading">
                        <span class="ct-panel-icon"><i class="bi bi-clock-history"></i></span>
                        <div class="ct-panel-title"><small>Dispatch queue</small><h2>Awaiting a driver</h2></div>
                    </div>
                </header>
                <div class="ct-panel-body">
                    <div class="ct-list">
                        @forelse ($pendingAssignmentOrders as $order)
                            <a href="{{ route('orders.edit', $order) }}" class="ct-row-card">
                                <span class="ct-row-icon"><i class="bi bi-box-seam-fill"></i></span>
                                <span class="ct-row-main"><strong>{{ $order->order_code }}</strong><span>{{ $order->receiver?->name ?? 'No receiver' }} · {{ $order->delivery_address }}</span><small>Expected {{ $order->expected_delivery_at?->format('M d, h:i A') ?? 'schedule not set' }}</small></span>
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        @empty
                            <x-dashboard.empty-state icon="bi-check2-circle" title="Dispatch queue is clear" message="There are no unassigned pending orders." />
                        @endforelse
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <section class="ct-panel">
        <header class="ct-panel-header">
            <div class="ct-panel-heading"><span class="ct-panel-icon green"><i class="bi bi-receipt"></i></span><div class="ct-panel-title"><small>Latest activity</small><h2>Recent orders</h2></div></div>
            <a href="{{ route('orders.index') }}" class="ct-panel-link">All orders <i class="bi bi-arrow-right"></i></a>
        </header>
        <div class="ct-panel-body ct-panel-body-flush">
            <div class="ct-table-wrap">
                <table class="ct-table">
                    <thead><tr><th>Order</th><th>Receiver</th><th>Driver</th><th>Products</th><th>Expected</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('orders.show', $order) }}"><strong>{{ $order->order_code }}</strong><small>{{ $order->created_at?->diffForHumans() }}</small></a></td>
                                <td>{{ $order->receiver?->name ?? 'N/A' }}</td>
                                <td>{{ $order->driver?->name ?? 'Not assigned' }}</td>
                                <td>{{ $order->orderItems->pluck('product.name')->filter()->take(2)->implode(', ') ?: 'N/A' }}</td>
                                <td>{{ $order->expected_delivery_at?->format('M d, h:i A') ?? 'Not set' }}</td>
                                <td><x-dashboard.status-badge :status="$order->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-dashboard.empty-state icon="bi-receipt" title="No orders yet" message="New order activity will appear here." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection
