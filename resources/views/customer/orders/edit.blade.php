@extends('layouts.app')

@section('title', 'Edit Order')

@section('content')
<div class="customer-order-page">
    <div class="customer-order-header">
        <div>
            <span class="eyebrow">Pending order request</span>
            <h1>Edit {{ $order->order_code }}</h1>
            <p>Update the products, requested schedule, or delivery location before the order is assigned.</p>
        </div>
        <a href="{{ route('customer.orders.show', $order) }}" class="secondary-button">Back to order</a>
    </div>

    @if ($errors->any())
        <div class="flash-message error">Please check the form and correct the highlighted fields.</div>
    @endif

    <form method="POST" action="{{ route('customer.orders.update', $order) }}" class="customer-order-form">
        @csrf
        @method('PUT')

        @include('customer.orders.partials.form', [
            'order' => $order,
            'customer' => $customer,
            'products' => $products,
            'googleMapsApiKey' => $googleMapsApiKey,
            'buttonText' => 'Save Order Changes',
            'cancelRoute' => route('customer.orders.show', $order),
        ])
    </form>
</div>
@endsection
