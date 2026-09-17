<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\TelemetryLog;
use App\Models\Trip;
use App\Models\User;
use App\Services\ColdChain\TemperatureStatusService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(
        TemperatureStatusService $temperatureStatusService
    ) {
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

        $activeDeliveryTrips->each(function (Trip $trip) use (
            $temperatureStatusService
        ) {
            $trip->setAttribute(
                'temperature_state',
                $temperatureStatusService->evaluate(
                    $trip->latestTelemetry?->temperature,
                    $trip->product
                )
            );
        });

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

        $setupSteps = $this->setupSteps();

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
            'setupSteps',
        ));
    }

    /**
     * An order cannot be dispatched until a product exists, a driver holds a
     * truck, and a device is paired to that truck. Those dependencies are not
     * visible anywhere else, so the dashboard states them plainly until they
     * are all satisfied.
     *
     * @return array<int, array<string, mixed>>
     */
    private function setupSteps(): array
    {
        $activeDrivers = User::query()
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('name', 'Driver'));

        return [
            [
                'label' => 'Product catalogue available',
                'detail' => 'Orders use the configured product catalogue. Contact the system maintainer if no products are available.',
                'done' => Product::query()->exists(),
                'url' => null,
                'action' => null,
            ],
            [
                'label' => 'Create a driver account',
                'detail' => 'Drivers sign in to start trips and report cargo condition.',
                'done' => (clone $activeDrivers)->exists(),
                'url' => route('users.create'),
                'action' => 'Add driver',
            ],
            [
                'label' => 'Register a truck and assign its driver',
                'detail' => 'An order requires a driver with a truck in service. Contact the system maintainer to configure truck assignments.',
                'done' => (clone $activeDrivers)->whereHas('assignedTruck')->exists(),
                'url' => null,
                'action' => null,
            ],
            [
                'label' => 'Pair a tracking device with that truck',
                'detail' => 'Readings reach a trip through the device on its truck. Contact the system maintainer to configure device pairing.',
                'done' => Device::query()->whereNotNull('truck_id')->exists(),
                'url' => null,
                'action' => null,
            ],
        ];
    }
}
