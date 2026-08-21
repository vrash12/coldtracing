@extends('layouts.app')

@section('title', 'Order Management')

@section('content')
@php
    $pendingCount = (int) ($stats['pending'] ?? 0);
    $assignedCount = (int) ($stats['assigned'] ?? 0);
    $inTransitCount = (int) ($stats['in_transit'] ?? 0);
    $deliveredCount = (int) ($stats['delivered'] ?? 0);
@endphp

<div class="ct-index">
    <header class="ct-index-header">
        <div>
            <small>Administrator workspace</small>
            <h1>Order management</h1>
            <p>Search, assign, and review delivery orders. Fleet locations remain in the dedicated live-monitoring page.</p>
        </div>
        <div class="ct-index-actions">
            <a href="{{ route('monitoring.index') }}" class="ct-button ct-button-light"><i class="bi bi-map-fill"></i>Live monitoring</a>
            <a href="{{ route('orders.create') }}" class="ct-button ct-button-dark"><i class="bi bi-plus-circle-fill"></i>Create order</a>
        </div>
    </header>

    @if (session('success'))
        <div class="ct-flash ct-flash-success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="ct-flash ct-flash-error"><i class="bi bi-exclamation-circle-fill"></i>{{ session('error') }}</div>
    @endif

    <section class="ct-metrics" aria-label="Order status summary">
        <x-dashboard.metric label="Awaiting assignment" :value="$pendingCount" icon="bi-hourglass-split" tone="amber" :href="route('orders.index', ['status' => 'pending'])" />
        <x-dashboard.metric label="Assigned" :value="$assignedCount" icon="bi-person-check-fill" tone="blue" :href="route('orders.index', ['status' => 'assigned'])" />
        <x-dashboard.metric label="In transit" :value="$inTransitCount" icon="bi-truck-front-fill" tone="cyan" :href="route('orders.index', ['status' => 'in_transit'])" />
        <x-dashboard.metric label="Delivered" :value="$deliveredCount" icon="bi-check-circle-fill" tone="green" :href="route('orders.index', ['status' => 'delivered'])" />
    </section>

    <section class="ct-panel">
        <div class="ct-filter-bar">
            <div class="ct-filter-copy">
                <strong>Orders</strong>
                <span>{{ $orders->total() }} result{{ $orders->total() === 1 ? '' : 's' }}{{ $search || $status ? ' for the current filter' : '' }}</span>
            </div>
            <form method="GET" action="{{ route('orders.index') }}" class="ct-filter">
                <label class="ct-search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Order, customer, driver, product...">
                </label>
                <select name="status" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'approved', 'assigned', 'in_transit', 'delivered', 'cancelled'] as $statusOption)
                        <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $statusOption)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="ct-button ct-button-dark ct-button-small"><i class="bi bi-funnel-fill"></i>Apply</button>
                @if ($search || $status)
                    <a href="{{ route('orders.index') }}" class="ct-button ct-button-light ct-button-small">Clear</a>
                @endif
            </form>
        </div>

        <div class="ct-panel-body ct-panel-body-flush">
            <div class="ct-table-wrap">
                <table class="ct-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Products</th><th>Driver</th><th>Destination</th><th>Expected</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td><strong>{{ $order->order_code }}</strong><small>{{ $order->created_at?->format('M d, Y · h:i A') }}</small></td>
                                <td><strong>{{ $order->receiver?->name ?? 'No receiver' }}</strong><small>{{ $order->receiver?->phone ?? $order->receiver?->email ?? 'No contact' }}</small></td>
                                <td><div class="ct-product-list">@forelse ($order->orderItems->take(2) as $item)<span>{{ $item->product?->name ?? 'N/A' }}</span><small>{{ $item->quantity }} {{ $item->unit }}</small>@empty<span>No products</span>@endforelse</div></td>
                                <td><strong>{{ $order->driver?->name ?? 'Not assigned' }}</strong><small>{{ $order->trip?->truck?->plate_number ?? 'No truck' }}</small></td>
                                <td><span class="ct-address" title="{{ $order->delivery_address }}">{{ $order->delivery_address }}</span></td>
                                <td><strong>{{ $order->expected_delivery_at?->format('M d, Y') ?? 'Not set' }}</strong><small>{{ $order->expected_delivery_at?->format('h:i A') }}</small></td>
                                <td><x-dashboard.status-badge :status="$order->status" /></td>
                                <td>
                                    <div class="ct-inline-actions">
                                        <a href="{{ route('orders.show', $order) }}" class="ct-icon-button" title="View order"><i class="bi bi-eye-fill"></i></a>
                                        @if (! in_array($order->status, ['in_transit', 'delivered', 'cancelled'], true))
                                            <a href="{{ route('orders.edit', $order) }}" class="ct-icon-button" title="Edit or assign"><i class="bi bi-pencil-fill"></i></a>
                                        @endif
                                        @if ($order->canBeCancelled())
                                            <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?');">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="ct-icon-button warning" title="Cancel order"><i class="bi bi-x-circle-fill"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-dashboard.empty-state icon="bi-bag" title="No orders found" message="Try clearing the filters or create a new delivery order." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($orders->hasPages())<div class="ct-pagination">{{ $orders->links() }}</div>@endif
    </section>
</div>
@endsection

@include('dashboard.partials.role-dashboard-styles')
@include('dashboard.partials.role-index-styles')
