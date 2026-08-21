@props([
    'icon' => 'bi-inbox',
    'title' => 'Nothing to show',
    'message' => null,
])

<div class="ct-empty">
    <span><i class="bi {{ $icon }}"></i></span>
    <strong>{{ $title }}</strong>
    @if ($message)<p>{{ $message }}</p>@endif
    {{ $slot }}
</div>
