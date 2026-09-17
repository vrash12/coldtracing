@extends('layouts.app')

@section('title', 'Edit Order')

@section('content')
<div class="ct-index customer-order-page">
    <header class="ct-index-header customer-order-header">
        <div>
            <small>Pending delivery request</small>
            <h1>Edit {{ $order->order_code }}</h1>
            <p>Make the needed changes while this request is still pending.</p>
        </div>
        <a href="{{ route('customer.orders.show', $order) }}" class="ct-button ct-button-light secondary-button"><i class="bi bi-arrow-left"></i>Order details</a>
    </header>

    @if ($errors->any())
        <div class="ct-flash ct-flash-error" role="alert"><i class="bi bi-exclamation-circle-fill"></i>Please correct the highlighted fields and submit again.</div>
    @endif

    <form method="POST" action="{{ route('customer.orders.update', $order) }}" class="customer-order-form">
        @csrf
        @method('PUT')

        @include('customer.orders.partials.form', [
            'order' => $order,
            'customer' => $customer,
            'products' => $products,
            'googleMapsApiKey' => $googleMapsApiKey,
            'buttonText' => 'Save changes',
            'cancelRoute' => route('customer.orders.show', $order),
        ])
    </form>
</div>
@endsection
