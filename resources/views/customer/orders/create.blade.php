@extends('layouts.app')

@section('title', 'Create Order')

@section('content')

<div class="ct-index customer-order-page">

    <header class="ct-index-header customer-order-header">
        <div>
            <small>New delivery request</small>
            <h1>Request a delivery</h1>
            <p>Add the items, choose where they should go, and submit the request for review.</p>
        </div>

        <a href="{{ route('customer.orders.index') }}" class="ct-button ct-button-light secondary-button">
            <i class="bi bi-arrow-left"></i>My orders
        </a>
    </header>

    @if ($errors->any())
        <div class="ct-flash ct-flash-error" role="alert">
            <i class="bi bi-exclamation-circle-fill"></i>Please correct the highlighted fields and submit again.
        </div>
    @endif

    <form method="POST" action="{{ route('customer.orders.store') }}" class="customer-order-form">
        @csrf

        @include('customer.orders.partials.form', [
            'order' => null,
            'customer' => $customer,
            'products' => $products,
            'googleMapsApiKey' => $googleMapsApiKey,
            'buttonText' => 'Submit request',
            'cancelRoute' => route('customer.orders.index'),
        ])
    </form>

</div>

@endsection
