@extends('layouts.app')

@section('title', 'Order ' . $order->order_code)

@section('content')
@php
    $status = strtolower((string) $order->status);
    $progressIndex = match ($status) {
        'pending' => 0,
        'approved', 'assigned' => 1,
        'in_transit' => 2,
        'delivered' => 3,
        default => -1,
    };
    $statusMessage = match ($status) {
        'pending' => 'Your request was sent and is waiting for administrator review.',
        'approved' => 'Your request is approved and waiting for a driver.',
        'assigned' => 'A driver has been assigned. The delivery is ready to begin.',
        'in_transit' => 'Your delivery is currently on the road.',
        'delivered' => 'Your delivery was completed.',
        'cancelled' => 'This request was cancelled.',
        default => 'The order status was updated.',
    };
@endphp

<div class="ct-index order-detail-page customer-order-show-page">
    <header class="ct-index-header order-detail-header">
        <div>
            <small>My order</small>
            <div class="order-title-row">
                <h1>{{ $order->order_code }}</h1>
                <x-dashboard.status-badge :status="$order->status" />
            </div>
            <p>{{ $statusMessage }}</p>
        </div>

        <div class="ct-index-actions">
            <a href="{{ route('customer.orders.index') }}" class="ct-button ct-button-light">
                <i class="bi bi-arrow-left"></i>My orders
            </a>
            @if ($canEdit)
                <a href="{{ route('customer.orders.edit', $order) }}" class="ct-button ct-button-dark">
                    <i class="bi bi-pencil-fill"></i>Edit request
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

    <section class="ct-panel order-progress-panel">
        @if ($status === 'cancelled')
            <div class="cancelled-order-message">
                <i class="bi bi-x-circle-fill"></i>
                <div><strong>Request cancelled</strong><span>No further delivery activity will occur for this order.</span></div>
            </div>
        @else
            <ol class="order-progress" aria-label="Delivery progress">
                @foreach ([
                    ['Request sent', 'bi-send-check-fill'],
                    ['Driver assigned', 'bi-person-check-fill'],
                    ['In transit', 'bi-truck-front-fill'],
                    ['Delivered', 'bi-check-circle-fill'],
                ] as $index => [$label, $icon])
                    <li class="{{ $progressIndex >= $index ? 'complete' : '' }} {{ $progressIndex === $index ? 'current' : '' }}">
                        <span><i class="bi {{ $icon }}"></i></span>
                        <strong>{{ $label }}</strong>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <div class="order-detail-grid">
        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon cyan"><i class="bi bi-box-seam-fill"></i></span>
                    <div class="ct-panel-title"><small>Contents</small><h2>Items</h2></div>
                </div>
            </header>
            <div class="ct-panel-body">
                <div class="order-product-list">
                    @forelse ($order->orderItems as $item)
                        <div class="order-product-row">
                            <span><strong>{{ $item->product?->name ?? 'Product unavailable' }}</strong></span>
                            <em>{{ $item->quantity }} {{ $item->unit }}</em>
                        </div>
                    @empty
                        <x-dashboard.empty-state icon="bi-box" title="No items listed" message="This request has no product details." />
                    @endforelse
                </div>
            </div>
        </section>

        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon"><i class="bi bi-geo-alt-fill"></i></span>
                    <div class="ct-panel-title"><small>Delivery</small><h2>Destination & schedule</h2></div>
                </div>
            </header>
            <div class="ct-panel-body">
                <dl class="order-facts">
                    <div>
                        <dt>Destination</dt>
                        <dd>{{ $order->delivery_address }}</dd>
                    </div>
                    <div>
                        <dt>Preferred delivery</dt>
                        <dd>{{ $order->expected_delivery_at?->format('M d, Y · h:i A') ?? 'No preferred time' }}</dd>
                    </div>
                    @if ($order->driver)
                        <div>
                            <dt>Driver</dt>
                            <dd>{{ $order->driver->name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Requested</dt>
                        <dd>{{ $order->created_at?->format('M d, Y · h:i A') }}</dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    @if ($order->notes)
        <section class="ct-panel order-notes-panel">
            <span class="ct-panel-icon amber"><i class="bi bi-journal-text"></i></span>
            <div><small>Delivery instructions</small><p>{{ $order->notes }}</p></div>
        </section>
    @endif

    @if ($canCancel || $canDelete)
        <section class="ct-panel request-actions-panel">
            <div>
                <h2>Manage this request</h2>
                <p>You can cancel it before a driver is assigned.</p>
            </div>
            <div class="request-actions">
                @if ($canCancel)
                    <form method="POST" action="{{ route('customer.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order request?');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="ct-button ct-button-danger">
                            <i class="bi bi-x-circle-fill"></i>Cancel request
                        </button>
                    </form>
                @endif

                @if ($canDelete)
                    <details class="more-order-actions">
                        <summary>More options</summary>
                        <form method="POST" action="{{ route('customer.orders.destroy', $order) }}" onsubmit="return confirm('Permanently delete this pending order? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"><i class="bi bi-trash3-fill"></i>Permanently delete</button>
                        </form>
                    </details>
                @endif
            </div>
        </section>
    @endif
</div>
@endsection
