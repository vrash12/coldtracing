@extends('layouts.app')

@section('title', 'Edit User')

@section('content')

<div class="page-header">
    <div>
        <span class="eyebrow">Administrator module</span>
        <h1>Edit User</h1>
        <p>Update account details, role assignment, status, or password.</p>
    </div>

    <a href="{{ route('users.index') }}" class="secondary-button">
        Back to Users
    </a>
</div>

<x-form-errors />

<div class="form-panel">
    <form method="POST" action="{{ route('users.update', $user) }}" class="user-form">
        @csrf
        @method('PUT')

        @include('admin.users.partials.form', [
            'user' => $user,
            'roles' => $roles,
            'buttonText' => 'Update User',
            'isEdit' => true,
        ])
    </form>
</div>

@endsection

@push('styles')
@include('admin.users.partials.styles')
@endpush