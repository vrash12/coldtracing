@extends('layouts.app')

@section('title', 'My Trips')

@section('content')

<div class="ct-index">

    <header class="ct-index-header">
        <div>
            <small>Driver workspace</small>
            <h1>My trips</h1>
            <p>
                Every delivery run assigned to you, from the ones waiting to start through to your
                completed history.
            </p>
        </div>

        <div class="ct-index-actions">
            <a href="{{ route('driver.orders.index') }}" class="ct-button ct-button-dark">
                <i class="bi bi-signpost-split-fill"></i>
                Plan my route
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

    <section class="ct-metrics" aria-label="Trip summary">
        <x-dashboard.metric
            label="Ready to start"
            :value="$stats['pending']"
            detail="Trips awaiting departure"
            icon="bi-play-circle-fill"
            tone="amber"
            :href="route('driver.trips.index', ['status' => 'pending'])" />
        <x-dashboard.metric
            label="In progress"
            :value="$stats['in_progress']"
            detail="Deliveries underway now"
            icon="bi-truck-front-fill"
            tone="cyan"
            :href="route('driver.trips.index', ['status' => 'in_progress'])" />
        <x-dashboard.metric
            label="Completed"
            :value="$stats['completed']"
            detail="Deliveries you have finished"
            icon="bi-check-circle-fill"
            tone="green"
            :href="route('driver.trips.index', ['status' => 'completed'])" />
        <x-dashboard.metric
            label="All trips"
            :value="$stats['total']"
            detail="{{ $stats['cancelled'] }} cancelled"
            icon="bi-list-check"
            tone="violet"
            :href="route('driver.trips.index')" />
    </section>

    <section class="ct-panel">
        <div class="ct-filter-bar">
            <div class="ct-filter-copy">
                <strong>Trip history</strong>
                <span>Newest first. A trip is created for you when an administrator assigns an order.</span>
            </div>

            <form method="GET" action="{{ route('driver.trips.index') }}" class="ct-filter">
                <select name="status" aria-label="Filter trips by status">
                    <option value="">All statuses</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>In progress</option>
                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>

                <button type="submit" class="ct-button ct-button-primary">
                    <i class="bi bi-funnel-fill"></i>
                    Filter
                </button>

                @if ($status)
                    <a href="{{ route('driver.trips.index') }}" class="ct-button ct-button-light">Clear</a>
                @endif
            </form>
        </div>

        <div class="ct-table-wrap">
            <table class="ct-table ct-card-table">
                <thead>
                    <tr>
                        <th>Trip</th>
                        <th>Destination</th>
                        <th>Cargo</th>
                        <th>Truck</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($trips as $trip)
                        @php
                            $reading = $trip->latestTelemetry;
                            $minimum = $trip->product?->min_temp;
                            $maximum = $trip->product?->max_temp;

                            $conditionClass = 'neutral';
                            $conditionLabel = 'No reading yet';

                            if ($reading?->temperature !== null && $minimum !== null && $maximum !== null) {
                                $temperature = (float) $reading->temperature;

                                if ($temperature < (float) $minimum) {
                                    $conditionClass = 'warning';
                                    $conditionLabel = number_format($temperature, 1) . ' °C · Too low';
                                } elseif ($temperature > (float) $maximum) {
                                    $conditionClass = 'danger';
                                    $conditionLabel = number_format($temperature, 1) . ' °C · Too high';
                                } else {
                                    $conditionClass = 'safe';
                                    $conditionLabel = number_format($temperature, 1) . ' °C · Safe';
                                }
                            }
                        @endphp
                        <tr>
                            <td data-label="Trip">
                                <strong>{{ $trip->order?->order_code ?? 'Trip #' . $trip->id }}</strong>
                                <small>
                                    @if ($trip->started_at)
                                        Started {{ $trip->started_at->format('M d, Y h:i A') }}
                                    @else
                                        Created {{ $trip->created_at?->format('M d, Y h:i A') }}
                                    @endif
                                </small>
                            </td>

                            <td data-label="Destination">
                                <span class="ct-address">{{ $trip->destination_address ?? 'Destination not set' }}</span>
                                <small>{{ $trip->receiver?->name ?? 'No receiver recorded' }}</small>
                            </td>

                            <td data-label="Cargo">
                                <strong>{{ $trip->product?->name ?? 'Product not available' }}</strong>
                                <small>
                                    @if ($minimum !== null && $maximum !== null)
                                        Safe {{ number_format((float) $minimum, 1) }} – {{ number_format((float) $maximum, 1) }} °C
                                    @else
                                        No temperature limits set
                                    @endif
                                </small>
                            </td>

                            <td data-label="Truck">
                                <strong>{{ $trip->truck?->plate_number ?? 'No truck' }}</strong>
                                <small>{{ $trip->truck?->model ?? 'Model not set' }}</small>
                            </td>

                            <td data-label="Condition">
                                <strong class="ct-condition ct-condition-{{ $conditionClass }}">
                                    <i class="bi bi-thermometer-half"></i>
                                    {{ $conditionLabel }}
                                </strong>
                                <small>{{ $reading?->recorded_at?->diffForHumans() ?? 'Awaiting first reading' }}</small>
                            </td>

                            <td data-label="Status">
                                <x-dashboard.status-badge :status="$trip->status" />
                            </td>

                            <td data-label="Action">
                                <a href="{{ route('driver.trips.show', $trip) }}" class="ct-row-action">
                                    <i class="bi bi-arrow-right-circle-fill"></i>
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-dashboard.empty-state
                                    icon="bi-signpost-2"
                                    title="No trips to show"
                                    message="A trip appears here as soon as an administrator assigns a delivery order to you." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ct-pagination">
            {{ $trips->links() }}
        </div>
    </section>

</div>

@endsection
