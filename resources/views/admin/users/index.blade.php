@extends('layouts.app')

@section('title', 'User Management')

@section('content')
@php
    $driverRole = $roles->firstWhere('name', 'Driver');
    $receiverRole = $roles->firstWhere('name', 'Receiver');
@endphp

<div class="ct-index">
    <header class="ct-index-header">
        <div>
            <small>Administrator workspace</small>
            <h1>User management</h1>
            <p>Find accounts, review access, and maintain the three supported system roles from one place.</p>
        </div>
        <div class="ct-index-actions">
            <a href="{{ route('users.create') }}" class="ct-button ct-button-dark"><i class="bi bi-person-plus-fill"></i>Add user</a>
        </div>
    </header>

    @if (session('success'))
        <div class="ct-flash ct-flash-success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="ct-flash ct-flash-error"><i class="bi bi-exclamation-circle-fill"></i>{{ session('error') }}</div>
    @endif

    <section class="ct-metrics" aria-label="User account summary">
        <x-dashboard.metric label="All accounts" :value="$stats['total']" icon="bi-people-fill" tone="blue" :href="route('users.index')" />
        <x-dashboard.metric label="Active" :value="$stats['active']" icon="bi-person-check-fill" tone="green" :href="route('users.index', ['status' => 'active'])" />
        <x-dashboard.metric label="Drivers" :value="$stats['drivers']" icon="bi-truck-front-fill" tone="cyan" :href="$driverRole ? route('users.index', ['role_id' => $driverRole->id]) : null" />
        <x-dashboard.metric label="Customers" :value="$stats['receivers']" icon="bi-box2-heart-fill" tone="violet" :href="$receiverRole ? route('users.index', ['role_id' => $receiverRole->id]) : null" />
    </section>

    <section class="ct-panel">
        <div class="ct-filter-bar">
            <div class="ct-filter-copy">
                <strong>Accounts</strong>
                <span>{{ $users->total() }} result{{ $users->total() === 1 ? '' : 's' }}{{ $search || $roleId || $status ? ' for the current filter' : '' }}</span>
            </div>
            <form method="GET" action="{{ route('users.index') }}" class="ct-filter">
                <label class="ct-search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Name, email, or phone...">
                </label>
                <select name="role_id" aria-label="Filter by role">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ (string) $roleId === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
                <select name="status" aria-label="Filter by account status">
                    <option value="">All statuses</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button type="submit" class="ct-button ct-button-dark ct-button-small"><i class="bi bi-funnel-fill"></i>Apply</button>
                @if ($search || $roleId || $status)
                    <a href="{{ route('users.index') }}" class="ct-button ct-button-light ct-button-small">Clear</a>
                @endif
            </form>
        </div>

        <div class="ct-panel-body ct-panel-body-flush">
            <div class="ct-table-wrap">
                <table class="ct-table">
                    <thead><tr><th>User</th><th>Role</th><th>Contact</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td>
                                <td><x-dashboard.role-badge :role="$user->role?->name" /></td>
                                <td><strong>{{ $user->phone ?? 'No phone' }}</strong></td>
                                <td><x-dashboard.status-badge :status="$user->status" /></td>
                                <td><strong>{{ $user->created_at?->format('M d, Y') }}</strong><small>{{ $user->created_at?->format('h:i A') }}</small></td>
                                <td>
                                    <div class="ct-inline-actions">
                                        <a href="{{ route('users.show', $user) }}" class="ct-icon-button" title="View user"><i class="bi bi-eye-fill"></i></a>
                                        <a href="{{ route('users.edit', $user) }}" class="ct-icon-button" title="Edit user"><i class="bi bi-pencil-fill"></i></a>
                                        @if (auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Delete this user account? This action cannot be undone.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="ct-icon-button danger" title="Delete user"><i class="bi bi-trash3-fill"></i></button>
                                            </form>
                                        @else
                                            <span class="ct-current-user">You</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-dashboard.empty-state icon="bi-people" title="No users found" message="Clear the filters or create a new account." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())<div class="ct-pagination">{{ $users->links() }}</div>@endif
    </section>
</div>
@endsection

@include('dashboard.partials.role-dashboard-styles')
@include('dashboard.partials.role-index-styles')
