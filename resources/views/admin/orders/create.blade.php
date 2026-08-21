@extends('layouts.app')

@section('title', 'Create Order')

@section('content')

<div class="orders-page">

    <div class="page-toolbar">
        <div>
            <h1>Create Order</h1>
        </div>

        <a href="{{ route('orders.index') }}" class="secondary-button">
            Back to Orders
        </a>
    </div>

    <div class="form-panel">
        <form method="POST" action="{{ route('orders.store') }}">
            @csrf

            @include('admin.orders.partials.form', [
                'order' => null,
                'products' => $products,
                'receivers' => $receivers,
                'buttonText' => 'Create Order',
            ])
        </form>
    </div>

</div>

@endsection

@push('styles')
@include('admin.orders.partials.styles')
@endpush