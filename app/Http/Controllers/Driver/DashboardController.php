<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Order;
use App\Models\Trip;
use App\Notifications\OrderAssignedToDriverNotification;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private function authorizeDriver(): void
    {
        if (! Auth::check() || ! Auth::user()->isDriver()) {
            abort(403, 'Only drivers can access this page.');
        }
    }

    public function index()
    {
        $this->authorizeDriver();

        $driver = Auth::user();

        $driver->load('assignedTruck.devices');

        $tripCounts = Trip::query()
            ->where('driver_id', $driver->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingTrips = (int) ($tripCounts['pending'] ?? 0);
        $activeTrips = (int) ($tripCounts['in_progress'] ?? 0);
        $assignedOrders = Order::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['assigned', 'in_transit'])
            ->count();

        $currentTrips = Trip::with([
            'truck',
            'product',
            'receiver',
            'order',
            'latestTelemetry.device',
        ])
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->latest('started_at')
            ->latest('id')
            ->take(5)
            ->get();

        $openAlerts = Alert::with([
            'trip.truck',
            'trip.product',
        ])
            ->whereHas('trip', function ($query) use ($driver) {
                $query->where('driver_id', $driver->id);
            })
            ->where('is_resolved', false)
            ->latest()
            ->take(4)
            ->get();

        $unreadNotificationCount = $driver->unreadNotifications()
            ->where('type', OrderAssignedToDriverNotification::class)
            ->count();

        $unreadOrderNotifications = $driver->unreadNotifications()
            ->where('type', OrderAssignedToDriverNotification::class)
            ->latest()
            ->take(4)
            ->get();

        $latestTelemetryAt = $currentTrips
            ->pluck('latestTelemetry.recorded_at')
            ->filter()
            ->sortDesc()
            ->first();

        return view('driver.dashboard', compact(
            'driver',
            'pendingTrips',
            'activeTrips',
            'assignedOrders',
            'currentTrips',
            'openAlerts',
            'unreadNotificationCount',
            'unreadOrderNotifications',
            'latestTelemetryAt',
        ));
    }
}
