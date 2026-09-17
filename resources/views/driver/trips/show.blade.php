@extends('layouts.app')

@section('title', 'Trip Details')

@section('content')

@php
    $reading = $trip->latestTelemetry;
    $conditionClass = $temperatureState['class'] === 'critical'
        ? 'danger'
        : $temperatureState['class'];
    $conditionLabel = $temperatureState['temperature'] === null
        ? 'No current reading'
        : number_format($temperatureState['temperature'], 1) . ' °C · ' . $temperatureState['label'];
    $openAlerts = $trip->alerts->where('is_resolved', false);
@endphp

<div class="ct-index">

    <header class="ct-index-header">
        <div>
            <small>Driver workspace</small>
            <h1>{{ $trip->order?->order_code ?? 'Trip #' . $trip->id }}</h1>
            <p>
                <i class="bi bi-geo-alt-fill"></i>
                {{ $trip->destination_address ?? 'Destination not set' }}
            </p>
        </div>

        <div class="ct-index-actions">
            @if ($mapsUrl)
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="ct-button ct-button-dark">
                    <i class="bi bi-navigation-fill"></i>
                    Navigate
                </a>
            @endif

            @if ($trip->status === 'pending')
                <form method="POST" action="{{ route('driver.trips.start', $trip) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="ct-button ct-button-primary">
                        <i class="bi bi-play-fill"></i>
                        Start trip
                    </button>
                </form>
            @elseif ($trip->status === 'in_progress')
                <form
                    method="POST"
                    action="{{ route('driver.trips.complete', $trip) }}"
                    data-confirm="Mark this delivery as completed? The order becomes delivered and your truck is released." data-confirm-action="Complete delivery"
                >
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="ct-button ct-button-primary">
                        <i class="bi bi-check2-circle"></i>
                        Complete trip
                    </button>
                </form>
            @endif

            <a href="{{ route('driver.trips.index') }}" class="ct-button ct-button-light">
                Back to trips
            </a>
        </div>
    </header>

    @if (session('success'))
        <div class="ct-flash ct-flash-success">
            <i class="bi bi-check-circle-fill"></i>
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="ct-flash ct-flash-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            {{ session('error') }}
        </div>
    @endif

    <section class="ct-metrics" aria-label="Trip condition summary">
        <x-dashboard.metric
            label="Trip status"
            :value="ucfirst(str_replace('_', ' ', $trip->status))"
            :detail="$trip->started_at ? 'Started ' . $trip->started_at->diffForHumans() : 'Not started yet'"
            icon="bi-truck-front-fill"
            tone="cyan" />
        <x-dashboard.metric
            label="Cargo temperature"
            :value="$conditionLabel"
            :detail="$reading?->recorded_at ? 'Recorded ' . $reading->recorded_at->diffForHumans() : 'Awaiting first reading'"
            icon="bi-thermometer-half"
            :tone="$temperatureState['is_breach'] ? 'red' : 'green'" />
        <x-dashboard.metric
            label="Mean kinetic temp."
            :value="$reading?->mkt_value !== null ? number_format((float) $reading->mkt_value, 2) . ' °C' : 'Not available'"
            detail="Calculated across the whole trip"
            icon="bi-graph-up"
            tone="violet" />
        <x-dashboard.metric
            label="Shelf life left"
            :value="$reading?->rsl_hours !== null ? number_format((float) $reading->rsl_hours, 1) . ' h' : 'Not available'"
            detail="Needs a complete product profile"
            icon="bi-hourglass-split"
            tone="amber" />
    </section>

    <div class="ct-grid-main">

        <section class="ct-panel">
            <header class="ct-panel-header">
                <div class="ct-panel-heading">
                    <span class="ct-panel-icon cyan"><i class="bi bi-broadcast-pin"></i></span>
                    <div class="ct-panel-title">
                        <small>Sensor history</small>
                        <h2>Recent readings</h2>
                    </div>
                </div>
            </header>

            <div class="ct-panel-body">
                <div class="ct-table-wrap">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th>Recorded</th>
                                <th>Temperature</th>
                                <th>Humidity</th>
                                <th>MKT</th>
                                <th>Shelf life</th>
                                <th>Position</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($trip->telemetryLogs as $log)
                                @php
                                    $logClass = 'neutral';

                                    if (
                                        $log->temperature !== null
                                        && $temperatureState['minimum'] !== null
                                        && $temperatureState['maximum'] !== null
                                    ) {
                                        $logTemperature = (float) $log->temperature;
                                        $logClass = $logTemperature < $temperatureState['minimum']
                                            ? 'warning'
                                            : ($logTemperature > $temperatureState['maximum'] ? 'danger' : 'safe');
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $log->recorded_at?->format('M d, h:i A') ?? 'Unknown' }}</strong>
                                        <small>{{ $log->recorded_at?->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        <strong class="ct-condition ct-condition-{{ $logClass }}">
                                            {{ $log->temperature !== null ? number_format((float) $log->temperature, 2) . ' °C' : 'No reading' }}
                                        </strong>
                                    </td>
                                    <td>{{ $log->humidity !== null ? number_format((float) $log->humidity, 1) . ' %' : '—' }}</td>
                                    <td>{{ $log->mkt_value !== null ? number_format((float) $log->mkt_value, 2) . ' °C' : '—' }}</td>
                                    <td>{{ $log->rsl_hours !== null ? number_format((float) $log->rsl_hours, 1) . ' h' : '—' }}</td>
                                    <td>
                                        @if ($log->latitude !== null && $log->longitude !== null)
                                            <small>{{ number_format((float) $log->latitude, 5) }}, {{ number_format((float) $log->longitude, 5) }}</small>
                                        @else
                                            <small>No valid fix</small>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-dashboard.empty-state
                                            icon="bi-broadcast"
                                            title="No readings yet"
                                            message="Readings appear once the trip has started and the truck's device reports in." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <aside class="ct-stack">

            <section class="ct-panel">
                <header class="ct-panel-header">
                    <div class="ct-panel-heading">
                        <span class="ct-panel-icon cyan"><i class="bi bi-box-seam-fill"></i></span>
                        <div class="ct-panel-title">
                            <small>Assignment</small>
                            <h2>Delivery details</h2>
                        </div>
                    </div>
                </header>

                <div class="ct-panel-body">
                    <div class="ct-detail">
                        <span>Status</span>
                        <strong><x-dashboard.status-badge :status="$trip->status" /></strong>
                    </div>
                    <div class="ct-detail">
                        <span>Cargo</span>
                        <strong>{{ $trip->product?->name ?? 'Product not available' }}</strong>
                    </div>
                    <div class="ct-detail">
                        <span>Safe range</span>
                        <strong>
                            @if ($temperatureState['minimum'] !== null && $temperatureState['maximum'] !== null)
                                {{ number_format($temperatureState['minimum'], 1) }} – {{ number_format($temperatureState['maximum'], 1) }} °C
                            @else
                                No limits set
                            @endif
                        </strong>
                    </div>
                    <div class="ct-detail">
                        <span>Receiver</span>
                        <strong>{{ $trip->receiver?->name ?? 'No receiver recorded' }}</strong>
                    </div>
                    <div class="ct-detail">
                        <span>Truck</span>
                        <strong>{{ $trip->truck?->plate_number ?? 'No truck' }}{{ $trip->truck?->model ? ' · ' . $trip->truck->model : '' }}</strong>
                    </div>
                    <div class="ct-detail">
                        <span>Pickup</span>
                        <strong>{{ $trip->origin_address ?? 'Not set' }}</strong>
                    </div>
                    <div class="ct-detail">
                        <span>Destination</span>
                        <strong>{{ $trip->destination_address ?? 'Not set' }}</strong>
                    </div>
                    <div class="ct-detail">
                        <span>Expected delivery</span>
                        <strong>{{ $trip->order?->expected_delivery_at?->format('M d, Y h:i A') ?? 'Not scheduled' }}</strong>
                    </div>
                    @if ($trip->completed_at)
                        <div class="ct-detail">
                            <span>Completed</span>
                            <strong>{{ $trip->completed_at->format('M d, Y h:i A') }}</strong>
                        </div>
                    @endif
                    @if ($trip->order?->notes)
                        <div class="ct-detail">
                            <span>Handling notes</span>
                            <strong>{{ $trip->order->notes }}</strong>
                        </div>
                    @endif
                </div>
            </section>

            <section class="ct-panel">
                <header class="ct-panel-header">
                    <div class="ct-panel-heading">
                        <span class="ct-panel-icon red"><i class="bi bi-exclamation-triangle-fill"></i></span>
                        <div class="ct-panel-title">
                            <small>{{ $openAlerts->count() }} open</small>
                            <h2>Temperature alerts</h2>
                        </div>
                    </div>
                </header>

                <div class="ct-panel-body">
                    <div class="ct-list">
                        @forelse ($trip->alerts as $alert)
                            <article class="ct-alert">
                                <strong>
                                    <span>{{ ucfirst(str_replace('_', ' ', $alert->type)) }}</span>
                                    <span>{{ $alert->is_resolved ? 'Resolved' : ucfirst($alert->severity) }}</span>
                                </strong>
                                <p>
                                    {{ $alert->message }}
                                    @if ($alert->is_resolved && $alert->resolved_at)
                                        · Cleared {{ $alert->resolved_at->diffForHumans() }}
                                    @endif
                                </p>
                            </article>
                        @empty
                            <x-dashboard.empty-state
                                icon="bi-shield-check"
                                title="No alerts on this trip"
                                message="An alert is raised automatically if the cargo leaves its safe temperature range." />
                        @endforelse
                    </div>
                </div>
            </section>

        </aside>
    </div>

</div>

@endsection
