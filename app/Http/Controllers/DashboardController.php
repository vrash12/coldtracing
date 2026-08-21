<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Order;
use App\Models\TelemetryLog;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        if (! Auth::check() || ! Auth::user()->isAdministrator()) {
            abort(403, 'Only administrators can access this dashboard.');
        }

        $orderCounts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingOrders = (int) ($orderCounts['pending'] ?? 0);
        $activeOrders = (int) ($orderCounts['assigned'] ?? 0)
            + (int) ($orderCounts['in_transit'] ?? 0);
        $inTransitOrders = (int) ($orderCounts['in_transit'] ?? 0);
        $deliveredToday = Order::query()
            ->where('status', 'delivered')
            ->whereDate('updated_at', today())
            ->count();

        $reportingDevices = Device::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(15))
            ->count();
        $assignedDevices = Device::whereNotNull('truck_id')->count();
        $devicesNeedingAttention = max(0, $assignedDevices - $reportingDevices);

        $unresolvedAlerts = Alert::where('is_resolved', false)->count();
        $criticalAlerts = Alert::query()
            ->where('severity', 'critical')
            ->where('is_resolved', false)
            ->count();

        $activeDeliveryTrips = Trip::with([
            'order',
            'truck',
            'product',
            'driver',
            'receiver',
            'latestTelemetry.device',
        ])
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->latest('started_at')
            ->latest('id')
            ->take(6)
            ->get();

        $pendingAssignmentOrders = Order::with([
            'receiver',
            'orderItems.product',
        ])
            ->where('status', 'pending')
            ->whereNull('driver_id')
            ->orderByRaw('expected_delivery_at IS NULL')
            ->orderBy('expected_delivery_at')
            ->take(5)
            ->get();

        $recentOrders = Order::with([
            'receiver',
            'driver',
            'orderItems.product',
        ])
            ->latest()
            ->take(6)
            ->get();

        $latestTelemetryAt = TelemetryLog::query()
            ->latest('recorded_at')
            ->first()?->recorded_at;

        return view('dashboard', compact(
            'pendingOrders',
            'activeOrders',
            'inTransitOrders',
            'deliveredToday',
            'reportingDevices',
            'assignedDevices',
            'devicesNeedingAttention',
            'unresolvedAlerts',
            'criticalAlerts',
            'activeDeliveryTrips',
            'pendingAssignmentOrders',
            'recentOrders',
            'latestTelemetryAt',
        ));
    }
}
