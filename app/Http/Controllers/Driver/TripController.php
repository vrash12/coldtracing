<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    private function authorizeDriver(): void
    {
        if (! Auth::check() || ! Auth::user()->isDriver()) {
            abort(403, 'Only drivers can access this page.');
        }
    }

    private function authorizeDriverTrip(Trip $trip): void
    {
        $this->authorizeDriver();

        if ((int) $trip->driver_id !== (int) Auth::id()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeDriver();

        $driver = Auth::user();
        $status = $request->input('status');

        $trips = Trip::with([
            'order',
            'truck',
            'product',
            'receiver',
            'latestTelemetry',
        ])
            ->where('driver_id', $driver->id)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => Trip::where('driver_id', $driver->id)->count(),
            'pending' => Trip::where('driver_id', $driver->id)
                ->where('status', 'pending')
                ->count(),
            'in_progress' => Trip::where('driver_id', $driver->id)
                ->where('status', 'in_progress')
                ->count(),
            'completed' => Trip::where('driver_id', $driver->id)
                ->where('status', 'completed')
                ->count(),
            'cancelled' => Trip::where('driver_id', $driver->id)
                ->where('status', 'cancelled')
                ->count(),
        ];

        return view('driver.trips.index', compact(
            'trips',
            'stats',
            'status'
        ));
    }

    public function show(Trip $trip)
    {
        $this->authorizeDriverTrip($trip);

        $trip->load([
            'order',
            'truck',
            'product',
            'receiver',
            'latestTelemetry',
            'telemetryLogs' => function ($query) {
                $query->latest('recorded_at')->take(10);
            },
            'alerts' => function ($query) {
                $query->latest()->take(10);
            },
        ]);

        $mapsUrl = $this->buildGoogleMapsUrl($trip);

        return view('driver.trips.show', compact(
            'trip',
            'mapsUrl'
        ));
    }

    public function start(Request $request, Trip $trip)
    {
        $this->authorizeDriverTrip($trip);

        try {
            DB::transaction(function () use ($trip) {
                $lockedTrip = Trip::with(['order', 'truck'])
                    ->lockForUpdate()
                    ->findOrFail($trip->id);

                if ($lockedTrip->status !== 'pending') {
                    throw new \RuntimeException('Only pending trips can be started.');
                }

                if ($lockedTrip->order?->status === 'cancelled') {
                    throw new \RuntimeException('The related order has already been cancelled.');
                }

                if ($lockedTrip->order?->status === 'delivered') {
                    throw new \RuntimeException('The related order has already been delivered.');
                }

                $lockedTrip->update([
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'completed_at' => null,
                ]);

                $lockedTrip->order?->update([
                    'status' => 'in_transit',
                ]);

                $lockedTrip->truck?->update([
                    'status' => 'in_trip',
                ]);
            });
        } catch (\RuntimeException $exception) {
            return $this->redirectAfterTripAction($request, $trip)
                ->with('error', $exception->getMessage());
        }

        return $this->redirectAfterTripAction($request, $trip)
            ->with(
                'success',
                'Trip started successfully. The order is now in transit and the truck is marked in trip.'
            );
    }

    public function complete(Request $request, Trip $trip)
    {
        $this->authorizeDriverTrip($trip);

        try {
            DB::transaction(function () use ($trip) {
                $lockedTrip = Trip::with(['order', 'truck'])
                    ->lockForUpdate()
                    ->findOrFail($trip->id);

                if ($lockedTrip->status !== 'in_progress') {
                    throw new \RuntimeException('Only active trips can be completed.');
                }

                $lockedTrip->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                $lockedTrip->order?->update([
                    'status' => 'delivered',
                ]);

                if (
                    $lockedTrip->truck_id
                    && ! $this->truckHasAnotherActiveTrip(
                        $lockedTrip->truck_id,
                        $lockedTrip->id
                    )
                ) {
                    $lockedTrip->truck?->update([
                        'status' => 'available',
                    ]);
                }
            });
        } catch (\RuntimeException $exception) {
            return $this->redirectAfterTripAction($request, $trip)
                ->with('error', $exception->getMessage());
        }

        return $this->redirectAfterTripAction($request, $trip)
            ->with(
                'success',
                'Trip completed successfully. The order is now delivered and the truck is available.'
            );
    }

    private function redirectAfterTripAction(Request $request, Trip $trip): RedirectResponse
    {
        if ($request->input('return_to') === 'dashboard') {
            return redirect()->route('driver.dashboard');
        }

        return redirect()->route('driver.trips.show', $trip);
    }

    private function truckHasAnotherActiveTrip(int $truckId, int $ignoreTripId): bool
    {
        return Trip::query()
            ->where('truck_id', $truckId)
            ->where('status', 'in_progress')
            ->where('id', '!=', $ignoreTripId)
            ->exists();
    }

    private function buildGoogleMapsUrl(Trip $trip): string
    {
        $origin = $trip->origin_lat !== null && $trip->origin_lng !== null
            ? $trip->origin_lat.','.$trip->origin_lng
            : $trip->origin_address;

        $destination = $trip->destination_lat !== null && $trip->destination_lng !== null
            ? $trip->destination_lat.','.$trip->destination_lng
            : $trip->destination_address;

        return 'https://www.google.com/maps/dir/?api=1'
            .'&origin='.urlencode((string) $origin)
            .'&destination='.urlencode((string) $destination)
            .'&travelmode=driving';
    }
}
