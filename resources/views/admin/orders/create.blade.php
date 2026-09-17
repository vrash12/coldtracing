@extends('layouts.app')

@section('title', 'Create Order')

@section('content')

<div class="ct-index orders-page order-workflow-page">

    <header class="ct-index-header page-toolbar">
        <div>
            <small>Administrator workspace</small>
            <h1>Create an order</h1>
            <p>Enter the request details, then assign a driver now or leave it pending.</p>
        </div>

        <a href="{{ route('orders.index') }}" class="ct-button ct-button-light secondary-button">
            <i class="bi bi-arrow-left"></i>Orders
        </a>
    </header>

    <x-form-errors />

    <div class="form-panel">
        <form method="POST" action="{{ route('orders.store') }}" class="admin-order-form">
            @csrf

            @include('admin.orders.partials.form', [
                'order' => null,
                'products' => $products,
                'receivers' => $receivers,
                'buttonText' => 'Create order',
            ])
        </form>
    </div>

</div>

@endsection
