@extends('layouts.app')

@section('title', 'Order ' . $order->order_code)

@section('content')
@php
    $canEdit = ! in_array($order->status, ['in_transit', 'delivered', 'cancelled'], true)
        && ! in_array($order->trip?->status, ['in_progress', 'completed', 'cancelled'], true);
@endphp

<div class="ct-index order-detail-page admin-order-detail-page">
    <header class="ct-index-header order-detail-header">
        <div>
            <small>Order management</small>
            <div class="order-title-row">
                <h1>{{ $order->order_code }}</h1>
                <x-dashboard.status-badge :status="$order->status" />
            </div>
            <p>
                {{ $order->driver
                    ? 'Assigned to ' . $order->driver->name . '.'
                    : 'This order is waiting for a driver assignment.'
                }}
            </p>
        </div>

        <div class="ct-index-actions">
            <a href="{{ route('orders.index') }}" class="ct-button ct-button-light">
                <i class="bi bi-arrow-left"></i>Orders
            </a>
            @if ($order->trip)
                <a href="{{ route('monitoring.index') }}" class="ct-button ct-button-light">
                    <i class="bi bi-map-fill"></i>Live monitoring
                </a>
            @endif
            @if ($canEdit)
                <a href="{{ route('orders.edit', $order) }}" class="ct-button ct-button-dark">
                    <i class="bi {{ $order->driver ? 'bi-pencil-fill' : 'bi-person-plus-fill' }}"></i>
                    {{ $order->driver ? 'Edit order' : 'Assign driver' }}
                </a>
            @endif
        </div>
    </header>

    @if (session('success'))
        <div class="ct-flash ct-flash-success" role="status"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="ct-flash ct-flash-error" role="alert"><i class="bi bi-exclamation-circle-fill"></i>{{ session('error') }}</div>
    @endif

    @if (! $order->driver && $canEdit)
        <a href="{{ route('orders.edit', $order) }}" class="dispatch-callout">
            <span><i class="bi bi-person-plus-fill"></i></span>
            <span><strong>Driver assignment needed</strong><small>Choose an available driver and delivery time to create the pending trip.</small></span>
            <i class="bi bi-arrow-right"></i>
        </a>
    @endif

    <div class="order-detail-grid">
        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon cyan"><i class="bi bi-box-seam-fill"></i></span>
                    <div class="ct-panel-title"><small>Cargo</small><h2>Order items</h2></div>
                </div>
            </header>
            <div class="ct-panel-body">
                <div class="order-product-list">
                    @forelse ($order->orderItems as $item)
                        <div class="order-product-row">
                            <span>
                                <strong>{{ $item->product?->name ?? 'Product unavailable' }}</strong>
                                @if ($item->product)
                                    <small>Safe range {{ $item->product->min_temp }}°C–{{ $item->product->max_temp }}°C</small>
                                @endif
                            </span>
                            <em>{{ $item->quantity }} {{ $item->unit }}</em>
                        </div>
                    @empty
                        <x-dashboard.empty-state icon="bi-box" title="No items listed" message="This order has no product details." />
                    @endforelse
                </div>
            </div>
        </section>

        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon"><i class="bi bi-truck-front-fill"></i></span>
                    <div class="ct-panel-title"><small>Dispatch</small><h2>Delivery details</h2></div>
                </div>
            </header>
            <div class="ct-panel-body">
                <dl class="order-facts">
                    <div>
                        <dt>Customer</dt>
                        <dd>{{ $order->receiver?->name ?? 'No customer selected' }}</dd>
                        @if ($order->receiver?->phone || $order->receiver?->email)
                            <small>{{ $order->receiver?->phone ?? $order->receiver?->email }}</small>
                        @endif
                    </div>
                    <div>
                        <dt>Driver</dt>
                        <dd>{{ $order->driver?->name ?? 'Not assigned' }}</dd>
                        @if ($order->trip?->truck)
                            <small>{{ $order->trip->truck->plate_number }}</small>
                        @endif
                    </div>
                    <div>
                        <dt>Delivery time</dt>
                        <dd>{{ $order->expected_delivery_at?->format('M d, Y · h:i A') ?? 'Not scheduled' }}</dd>
                    </div>
                    <div>
                        <dt>Destination</dt>
                        <dd>{{ $order->delivery_address }}</dd>
                    </div>
                    <div>
                        <dt>Created by</dt>
                        <dd>{{ $order->creator?->name ?? 'Unknown' }}</dd>
                        <small>{{ $order->created_at?->format('M d, Y · h:i A') }}</small>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    @if ($order->notes)
        <section class="ct-panel order-notes-panel">
            <span class="ct-panel-icon amber"><i class="bi bi-journal-text"></i></span>
            <div><small>Handling instructions</small><p>{{ $order->notes }}</p></div>
        </section>
    @endif
</div>
@endsection
