@extends('layouts.app')

@section('title', 'User Details')

@section('content')

<div class="page-header">
    <div>
        <span class="eyebrow">Administrator module</span>
        <h1>User Details</h1>
        <p>View account profile, assigned role, and account status.</p>
    </div>

    <div class="header-actions">
        <a href="{{ route('users.edit', $user) }}" class="primary-button">Edit User</a>
        <a href="{{ route('users.index') }}" class="secondary-button">Back to Users</a>
    </div>
</div>

<div class="profile-card">
    <div class="profile-top">
        <div class="profile-avatar">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>

        <div>
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>

            <div class="profile-badges">
                <span class="role-badge role-{{ strtolower($user->role?->name ?? 'none') }}">
                    {{ $user->role?->name ?? 'No Role' }}
                </span>

                <span class="status-badge status-{{ $user->status }}">
                    {{ ucfirst($user->status) }}
                </span>
            </div>
        </div>
    </div>

    <div class="details-grid">
        <div class="detail-item">
            <span>Phone</span>
            <strong>{{ $user->phone ?? 'N/A' }}</strong>
        </div>

        <div class="detail-item">
            <span>Role Description</span>
            <strong>{{ $user->role?->description ?? 'N/A' }}</strong>
        </div>

        <div class="detail-item">
            <span>Created At</span>
            <strong>{{ $user->created_at?->format('M d, Y h:i A') }}</strong>
        </div>

        <div class="detail-item">
            <span>Last Updated</span>
            <strong>{{ $user->updated_at?->format('M d, Y h:i A') }}</strong>
        </div>
    </div>
</div>

@endsection

@push('styles')
@include('admin.users.partials.styles')

<style>
    .profile-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .profile-top {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-bottom: 24px;
    }

    .profile-avatar {
        width: 76px;
        height: 76px;
        border-radius: 24px;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .profile-top h2 {
        margin: 0;
        color: #0f172a;
        font-size: 28px;
        font-weight: 800;
    }

    .profile-top p {
        margin: 6px 0 12px;
        color: #64748b;
    }

    .profile-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .detail-item {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 16px;
    }

    .detail-item span {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .detail-item strong {
        color: #0f172a;
        font-size: 14px;
        line-height: 1.5;
    }

    @media (max-width: 700px) {
        .profile-top {
            flex-direction: column;
            align-items: flex-start;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush