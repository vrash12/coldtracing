@extends('layouts.app')

@section('title', 'Create Order')

@section('content')

<div class="customer-order-page">

    <div class="customer-order-header">
        <div>
            <span class="eyebrow">Customer Order Request</span>
            <h1>Create New Order</h1>
            <p>Select your products, delivery schedule, and delivery location.</p>
        </div>

        <a href="{{ route('customer.orders.index') }}" class="secondary-button">
            Back to Orders
        </a>
    </div>

    @if ($errors->any())
        <div class="flash-message error">
            Please check the form and correct the highlighted fields.
        </div>
    @endif

    <form method="POST" action="{{ route('customer.orders.store') }}" class="customer-order-form">
        @csrf

        @include('customer.orders.partials.form', [
            'order' => null,
            'customer' => $customer,
            'products' => $products,
            'googleMapsApiKey' => $googleMapsApiKey,
            'buttonText' => 'Submit Order Request',
            'cancelRoute' => route('customer.orders.index'),
        ])
    </form>

</div>

@endsection