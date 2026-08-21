@props(['role'])

@php
    $normalizedRole = strtolower(str_replace(' ', '-', (string) ($role ?: 'No role')));
    $roleIcon = match ($normalizedRole) {
        'administrator' => 'bi-shield-lock-fill',
        'driver' => 'bi-truck-front-fill',
        'receiver' => 'bi-box2-heart-fill',
        default => 'bi-person-fill',
    };
@endphp

<span class="ct-role ct-role-{{ $normalizedRole }}">
    <i class="bi {{ $roleIcon }}"></i>
    {{ $role ?: 'No role' }}
</span>
