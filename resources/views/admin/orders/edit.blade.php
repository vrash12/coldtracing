@extends('layouts.app')

@section('title', 'Edit Order')

@section('content')

<div class="orders-page">

    <div class="page-toolbar">
        <div>
            <h1>Edit Order</h1>
        </div>

        <a href="{{ route('orders.index') }}" class="secondary-button">
            Back to Orders
        </a>
    </div>

    <div class="form-panel">
        <form method="POST" action="{{ route('orders.update', $order) }}">
            @csrf
            @method('PUT')

            @include('admin.orders.partials.form', [
                'order' => $order,
                'products' => $products,
                'receivers' => $receivers,
                'buttonText' => 'Update Order',
            ])
        </form>
    </div>

</div>

@endsection

@push('styles')
@include('admin.orders.partials.styles')
@endpush