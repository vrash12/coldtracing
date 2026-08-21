@props([
    'label',
    'value',
    'detail' => null,
    'icon' => 'bi-activity',
    'tone' => 'blue',
    'href' => null,
])

@if ($href)
    <a href="{{ $href }}" class="ct-metric ct-tone-{{ $tone }}">
        <span class="ct-metric-icon"><i class="bi {{ $icon }}"></i></span>
        <span class="ct-metric-copy">
            <span>{{ $label }}</span>
            <strong>{{ $value }}</strong>
            @if ($detail)<small>{{ $detail }}</small>@endif
        </span>
        <i class="bi bi-arrow-up-right ct-metric-arrow"></i>
    </a>
@else
    <article class="ct-metric ct-tone-{{ $tone }}">
        <span class="ct-metric-icon"><i class="bi {{ $icon }}"></i></span>
        <span class="ct-metric-copy">
            <span>{{ $label }}</span>
            <strong>{{ $value }}</strong>
            @if ($detail)<small>{{ $detail }}</small>@endif
        </span>
    </article>
@endif
