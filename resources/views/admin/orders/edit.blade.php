@extends('layouts.app')

@section('title', 'Edit Order')

@section('content')

<div class="ct-index orders-page order-workflow-page">

    <header class="ct-index-header page-toolbar">
        <div>
            <small>Administrator workspace</small>
            <h1>Edit {{ $order->order_code }}</h1>
            <p>Update the request or assign the driver and schedule.</p>
        </div>

        <a href="{{ route('orders.show', $order) }}" class="ct-button ct-button-light secondary-button">
            <i class="bi bi-arrow-left"></i>Order details
        </a>
    </header>

    <x-form-errors />

    <div class="form-panel">
        <form method="POST" action="{{ route('orders.update', $order) }}" class="admin-order-form">
            @csrf
            @method('PUT')

            @include('admin.orders.partials.form', [
                'order' => $order,
                'products' => $products,
                'receivers' => $receivers,
                'buttonText' => 'Save changes',
            ])
        </form>
    </div>

</div>

@endsection
