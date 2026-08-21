@extends('layouts.app')

@section('title', 'Order Details')

@section('content')

<div class="orders-page">

    <div class="page-toolbar">
        <div>
            <h1>Order Details</h1>
        </div>

        <div class="header-actions">
            <a href="{{ route('orders.edit', $order) }}" class="primary-button">
                Edit Order
            </a>

            <a href="{{ route('orders.index') }}" class="secondary-button">
                Back to Orders
            </a>
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

    <div class="details-panel">
        <div class="order-profile">
            <div class="order-icon">
                <i class="bi bi-bag-check"></i>
            </div>

            <div>
                <h2>{{ $order->order_code }}</h2>

                <p>
                    {{ $order->orderItems->count() }} product(s) in this order
                </p>

                <span class="status-badge status-{{ $order->status }}">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
            </div>
        </div>

        <div class="details-grid">

            <div class="detail-card">
                <span>Customer / Receiver</span>
                <strong>{{ $order->receiver?->name ?? 'N/A' }}</strong>
                <small>{{ $order->receiver?->email ?? 'N/A' }}</small>
            </div>

            <div class="detail-card">
                <span>Assigned Driver</span>
                <strong>{{ $order->driver?->name ?? 'No driver assigned' }}</strong>
                <small>{{ $order->driver?->email ?? 'N/A' }}</small>
            </div>

            <div class="detail-card">
                <span>Created By</span>
                <strong>{{ $order->creator?->name ?? 'N/A' }}</strong>
                <small>{{ $order->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</small>
            </div>

            <div class="detail-card">
                <span>Expected Delivery</span>
                <strong>{{ $order->expected_delivery_at?->format('M d, Y h:i A') ?? 'Not set' }}</strong>
            </div>

            <div class="detail-card full-width">
                <span>Delivery Address</span>
                <strong>{{ $order->delivery_address }}</strong>
                <small>
                    {{ $order->delivery_lat ?? 'No latitude' }},
                    {{ $order->delivery_lng ?? 'No longitude' }}
                </small>
            </div>

            <div class="detail-card full-width">
                <span>Products</span>

                @forelse ($order->orderItems as $item)
                    <strong>
                        {{ $item->product?->name ?? 'N/A' }} - {{ $item->quantity }} {{ $item->unit }}
                    </strong>

                    @if ($item->product)
                        <small>
                            Safe range:
                            {{ $item->product->min_temp ?? 'N/A' }}°C
                            to
                            {{ $item->product->max_temp ?? 'N/A' }}°C
                        </small>
                    @endif
                @empty
                    <strong>No products listed</strong>
                @endforelse
            </div>

            <div class="detail-card full-width">
                <span>Notes</span>
                <strong>{{ $order->notes ?: 'No notes' }}</strong>
            </div>

        </div>
    </div>

</div>

@endsection

@push('styles')
@include('admin.orders.partials.styles')
@endpush