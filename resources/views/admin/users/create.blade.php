@extends('layouts.app')

@section('title', 'Create User')

@section('content')

<div class="page-header">
    <div>
        <span class="eyebrow">Administrator module</span>
        <h1>Create User</h1>
        <p>Add a new ColdTrace account and assign a system role.</p>
    </div>

    <a href="{{ route('users.index') }}" class="secondary-button">
        Back to Users
    </a>
</div>

<x-form-errors />

<div class="form-panel">
    <form method="POST" action="{{ route('users.store') }}" class="user-form">
        @csrf

        @include('admin.users.partials.form', [
            'user' => null,
            'roles' => $roles,
            'buttonText' => 'Create User',
            'isEdit' => false,
        ])
    </form>
</div>

@endsection

@push('styles')
@include('admin.users.partials.styles')
@endpush