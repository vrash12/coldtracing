@props(['status'])

@php
    $normalizedStatus = strtolower((string) $status);
    $statusIcon = match ($normalizedStatus) {
        'in_progress', 'in_transit' => 'bi-truck-front-fill',
        'active', 'completed', 'delivered' => 'bi-check-circle-fill',
        'cancelled', 'inactive' => 'bi-x-circle-fill',
        'assigned', 'approved' => 'bi-person-check-fill',
        default => 'bi-clock-fill',
    };
@endphp

<span class="ct-status ct-status-{{ $normalizedStatus }}">
    <i class="bi {{ $statusIcon }}"></i>
    {{ ucfirst(str_replace('_', ' ', $normalizedStatus)) }}
</span>
