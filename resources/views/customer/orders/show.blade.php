@extends('layouts.app')

@section('title', 'Order Details')

@section('content')

<div class="customer-order-show-page">

    <div class="show-header">
        <div>
            <span class="eyebrow">Order Details</span>
            <h1>{{ $order->order_code }}</h1>
            <p>Review your order request, delivery address, products, and current status.</p>
        </div>

        <div class="header-actions">
            <a href="{{ route('customer.orders.index') }}" class="secondary-button">
                Back to Orders
            </a>

            @if ($canEdit)
                <a href="{{ route('customer.orders.edit', $order) }}" class="primary-button">
                    Edit Order
                </a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="flash-message success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="flash-message error">{{ session('error') }}</div>
    @endif

    <section class="details-panel">
        <div class="order-profile">
            <div class="order-icon">
                <i class="bi bi-bag-check"></i>
            </div>

            <div>
                <h2>{{ $order->order_code }}</h2>
                <p>{{ $order->orderItems->count() }} product item(s)</p>

                <span class="status-badge status-{{ $order->status }}">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-card">
                <span>Status</span>
                <strong>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</strong>
                <small>
                    @if ($order->status === 'pending')
                        Waiting for administrator review.
                    @elseif ($order->status === 'approved')
                        Approved and waiting for assignment.
                    @elseif ($order->status === 'assigned')
                        Assigned to delivery resources.
                    @elseif ($order->status === 'in_transit')
                        Your order is currently in transit.
                    @elseif ($order->status === 'delivered')
                        Delivery completed.
                    @else
                        This order has been cancelled.
                    @endif
                </small>
            </div>

            <div class="detail-card">
                <span>Created At</span>
                <strong>{{ $order->created_at?->format('M d, Y') }}</strong>
                <small>{{ $order->created_at?->format('h:i A') }}</small>
            </div>

            <div class="detail-card">
                <span>Expected Delivery</span>
                <strong>{{ $order->expected_delivery_at?->format('M d, Y') ?? 'Not set' }}</strong>
                <small>{{ $order->expected_delivery_at?->format('h:i A') ?? 'No preferred time provided' }}</small>
            </div>

            <div class="detail-card">
                <span>Delivery Address</span>
                <strong>{{ $order->delivery_address }}</strong>
                <small>{{ $order->delivery_lat }}, {{ $order->delivery_lng }}</small>
            </div>

            <div class="detail-card full-width">
                <span>Products</span>

                <div class="product-list">
                    @forelse ($order->orderItems as $item)
                        <div class="product-row">
                            <div>
                                <strong>{{ $item->product?->name ?? 'N/A' }}</strong>
                                <small>
                                    Safe range:
                                    {{ $item->product?->min_temp ?? 'N/A' }}°C
                                    to
                                    {{ $item->product?->max_temp ?? 'N/A' }}°C
                                </small>
                            </div>

                            <em>{{ $item->quantity }} {{ $item->unit }}</em>
                        </div>
                    @empty
                        <p class="muted">No products listed.</p>
                    @endforelse
                </div>
            </div>

            <div class="detail-card full-width">
                <span>Handling Notes</span>
                <strong>{{ $order->notes ?: 'No notes provided.' }}</strong>
            </div>
        </div>
    </section>

    @if ($canCancel || $canDelete)
        <section class="danger-panel">
            <div>
                <h2>Order Actions</h2>
                <p>
                    Pending orders can be deleted. Pending or approved orders can be cancelled.
                    Once an order is assigned or in transit, please contact the administrator.
                </p>
            </div>

            <div class="danger-actions">
                @if ($canCancel)
                    <form
                        method="POST"
                        action="{{ route('customer.orders.cancel', $order) }}"
                        onsubmit="return confirm('Cancel this order?');"
                    >
                        @csrf
                        @method('PATCH')

                        <button type="submit" class="cancel-button">
                            Cancel Order
                        </button>
                    </form>
                @endif

                @if ($canDelete)
                    <form
                        method="POST"
                        action="{{ route('customer.orders.destroy', $order) }}"
                        onsubmit="return confirm('Delete this pending order? This cannot be undone.');"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="delete-button">
                            Delete Order
                        </button>
                    </form>
                @endif
            </div>
        </section>
    @endif

</div>

@endsection

@push('styles')
<style>
    .customer-order-show-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .show-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 24px;
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

    .show-header h1 {
        margin: 0;
        color: #0f172a;
        font-size: 34px;
        font-weight: 900;
        letter-spacing: -0.05em;
    }

    .show-header p {
        margin: 8px 0 0;
        color: #64748b;
        line-height: 1.6;
    }

    .header-actions,
    .danger-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .primary-button,
    .secondary-button,
    .cancel-button,
    .delete-button {
        min-height: 44px;
        border: none;
        border-radius: 999px;
        padding: 0 18px;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
    }

    .primary-button {
        background: #2563eb;
        color: #ffffff;
    }

    .secondary-button {
        background: #f1f5f9;
        color: #475569;
    }

    .flash-message {
        padding: 14px 16px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
    }

    .flash-message.success {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .flash-message.error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .details-panel,
    .danger-panel {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        padding: 22px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .order-profile {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-bottom: 24px;
    }

    .order-icon {
        width: 72px;
        height: 72px;
        border-radius: 24px;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        flex-shrink: 0;
    }

    .order-profile h2 {
        margin: 0;
        color: #0f172a;
        font-size: 26px;
        font-weight: 900;
    }

    .order-profile p {
        margin: 6px 0 12px;
        color: #64748b;
    }

    .status-badge {
        display: inline-flex;
        min-height: 30px;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 900;
    }

    .status-pending {
        background: #fffbeb;
        color: #d97706;
    }

    .status-approved {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-assigned {
        background: #ecfeff;
        color: #0891b2;
    }

    .status-in_transit {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .status-delivered {
        background: #f0fdf4;
        color: #16a34a;
    }

    .status-cancelled {
        background: #fef2f2;
        color: #dc2626;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .detail-card {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 16px;
    }

    .detail-card.full-width {
        grid-column: 1 / -1;
    }

    .detail-card span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .detail-card strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        line-height: 1.5;
    }

    .detail-card small {
        display: block;
        color: #94a3b8;
        margin-top: 6px;
    }

    .product-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .product-row {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 13px;
    }

    .product-row em {
        font-style: normal;
        color: #2563eb;
        font-weight: 900;
        white-space: nowrap;
    }

    .muted {
        color: #94a3b8;
    }

    .danger-panel {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        border-color: #fecaca;
    }

    .danger-panel h2 {
        margin: 0;
        color: #0f172a;
        font-size: 20px;
        font-weight: 900;
    }

    .danger-panel p {
        margin: 6px 0 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }

    .cancel-button {
        background: #fffbeb;
        color: #d97706;
    }

    .delete-button {
        background: #fef2f2;
        color: #dc2626;
    }

    @media (max-width: 760px) {
        .show-header,
        .danger-panel,
        .order-profile {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-actions,
        .header-actions a,
        .danger-actions,
        .danger-actions form,
        .danger-actions button {
            width: 100%;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }

        .product-row {
            flex-direction: column;
        }
    }
</style>
@endpush
