@extends('layouts.app')

@section('title', 'Customer Dashboard')

@section('content')
<div class="ct-dashboard">
    <section class="ct-hero">
        <div class="ct-hero-copy">
            <span class="ct-eyebrow"><span class="ct-eyebrow-dot"></span>My deliveries</span>
            <h1>Hello, {{ $customer->name }}. Track what matters.</h1>
            <p>See where your active deliveries stand, check cargo condition, and review your latest order requests without operational clutter.</p>
            <div class="ct-hero-meta">
                <span><i class="bi bi-geo-alt-fill"></i>{{ $inTransitOrders }} currently in transit</span>
                <span><i class="bi bi-calendar-check-fill"></i>{{ $scheduledOrders }} scheduled</span>
                @if ($customer->permanent_delivery_address)
                    <span><i class="bi bi-house-check-fill"></i>Saved delivery address available</span>
                @endif
            </div>
        </div>
        <div class="ct-actions">
            <a href="{{ route('customer.orders.create') }}" class="ct-button ct-button-primary"><i class="bi bi-plus-circle-fill"></i>Request delivery</a>
            <a href="{{ route('customer.orders.index') }}" class="ct-button ct-button-secondary"><i class="bi bi-box-seam-fill"></i>All orders</a>
        </div>
    </section>

    <section class="ct-metrics" aria-label="Order summary">
        <x-dashboard.metric label="Pending review" :value="$pendingOrders" detail="Requests you can still update" icon="bi-hourglass-split" tone="amber" :href="route('customer.orders.index', ['status' => 'pending'])" />
        <x-dashboard.metric label="Scheduled" :value="$scheduledOrders" detail="Approved or driver assigned" icon="bi-calendar2-check-fill" tone="blue" :href="route('customer.orders.index')" />
        <x-dashboard.metric label="In transit" :value="$inTransitOrders" detail="Deliveries on the road" icon="bi-truck-front-fill" tone="cyan" :href="route('customer.orders.index', ['status' => 'in_transit'])" />
        <x-dashboard.metric label="Delivered" :value="$deliveredOrders" detail="Successfully completed orders" icon="bi-check-circle-fill" tone="green" :href="route('customer.orders.index', ['status' => 'delivered'])" />
    </section>

    <div class="ct-grid-main">
        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon cyan"><i class="bi bi-truck-front-fill"></i></span>
                    <div class="ct-panel-title"><small>Tracking</small><h2>Current deliveries</h2></div>
                </div>
            </header>
            <div class="ct-panel-body">
                <div class="ct-delivery-list">
                    @forelse ($currentDeliveries as $trip)
                        @php
                            $reading = $trip->latestTelemetry;
                            $temperatureState = $trip->temperature_state;
                            $temperature = $temperatureState['temperature'];
                            $conditionClass = $temperatureState['class'] === 'critical'
                                ? 'danger'
                                : $temperatureState['class'];
                            $conditionLabel = $temperature === null
                                ? 'Waiting for cargo update'
                                : number_format($temperature, 1) . ' °C · ' . $temperatureState['label'];
                            $conditionIcon = $temperatureState['icon'];
                        @endphp
                        <article class="ct-delivery">
                            <div class="ct-delivery-top">
                                <div class="ct-delivery-title">
                                    <strong>{{ $trip->order?->order_code ?? 'Delivery #' . $trip->id }}</strong>
                                    <span><i class="bi bi-geo-alt-fill"></i> {{ $trip->destination_address ?? 'Destination not set' }}</span>
                                </div>
                                <x-dashboard.status-badge :status="$trip->status" />
                            </div>
                            <div class="ct-delivery-details">
                                <div class="ct-detail"><span>Driver</span><strong>{{ $trip->driver?->name ?? 'Waiting for assignment' }}</strong></div>
                                <div class="ct-detail"><span>Cargo</span><strong>{{ $trip->product?->name ?? 'Product not available' }}</strong></div>
                                <div class="ct-detail"><span>Condition</span><strong class="ct-condition ct-condition-{{ $conditionClass }}"><i class="bi {{ $conditionIcon }}"></i>{{ $conditionLabel }}</strong></div>
                            </div>
                            <div class="ct-delivery-actions">
                                @if ($trip->order)
                                    <a href="{{ route('customer.orders.show', $trip->order) }}" class="ct-button ct-button-light ct-button-small"><i class="bi bi-eye-fill"></i>View order</a>
                                @endif
                                @if ($reading?->rsl_hours !== null)
                                    <span class="ct-button ct-button-small ct-static-chip"><i class="bi bi-hourglass-bottom"></i>{{ number_format((float) $reading->rsl_hours, 1) }} hours shelf life</span>
                                @endif
                                @if ($reading?->recorded_at)
                                    <span class="ct-button ct-button-small ct-static-chip"><i class="bi bi-clock"></i>Updated {{ $reading->recorded_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <x-dashboard.empty-state icon="bi-truck" title="No active deliveries" message="A delivery will appear here after an administrator assigns a driver and truck.">
                            <a href="{{ route('customer.orders.create') }}" class="ct-button ct-button-light ct-button-small">Create an order</a>
                        </x-dashboard.empty-state>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon"><i class="bi bi-clock-history"></i></span>
                    <div class="ct-panel-title"><small>Order activity</small><h2>Recent orders</h2></div>
                </div>
                <a href="{{ route('customer.orders.index') }}" class="ct-panel-link">View all</a>
            </header>
            <div class="ct-panel-body">
                <div class="ct-list">
                    @forelse ($latestOrders as $order)
                        <a href="{{ route('customer.orders.show', $order) }}" class="ct-row-card">
                            <span class="ct-row-icon"><i class="bi bi-box-seam-fill"></i></span>
                            <span class="ct-row-main">
                                <strong>{{ $order->order_code }}</strong>
                                <span>{{ $order->orderItems->pluck('product.name')->filter()->take(2)->implode(', ') ?: 'No product details' }}</span>
                                <small>{{ $order->expected_delivery_at?->format('M d, Y · h:i A') ?? 'Delivery schedule pending' }}</small>
                            </span>
                            <x-dashboard.status-badge :status="$order->status" />
                        </a>
                    @empty
                        <x-dashboard.empty-state icon="bi-box-seam" title="No orders yet" message="Submit your first delivery request to get started.">
                            <a href="{{ route('customer.orders.create') }}" class="ct-button ct-button-light ct-button-small">Request delivery</a>
                        </x-dashboard.empty-state>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
