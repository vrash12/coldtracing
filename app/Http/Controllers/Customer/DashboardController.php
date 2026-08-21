<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Only Receiver / Customer accounts can access this dashboard.
     */
    private function authorizeCustomer(): void
    {
        if (! Auth::check() || ! Auth::user()->isReceiver()) {
            abort(403, 'Only customers can access this dashboard.');
        }
    }

    /**
     * Display the customer dashboard.
     */
    public function index()
    {
        $this->authorizeCustomer();

        $customer = Auth::user();

        $orderCounts = Order::query()
            ->where('receiver_id', $customer->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingOrders = (int) ($orderCounts['pending'] ?? 0);
        $scheduledOrders = (int) ($orderCounts['approved'] ?? 0)
            + (int) ($orderCounts['assigned'] ?? 0);
        $inTransitOrders = (int) ($orderCounts['in_transit'] ?? 0);
        $deliveredOrders = (int) ($orderCounts['delivered'] ?? 0);

        $latestOrders = Order::with([
            'creator',
            'orderItems.product',
        ])
            ->where('receiver_id', $customer->id)
            ->latest()
            ->take(6)
            ->get();

        $currentDeliveries = Trip::with([
            'order',
            'truck',
            'product',
            'driver',
            'latestTelemetry.device',
        ])
            ->where('receiver_id', $customer->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->latest('started_at')
            ->latest('id')
            ->take(4)
            ->get();

        return view('customer.dashboard', compact(
            'customer',
            'pendingOrders',
            'scheduledOrders',
            'inTransitOrders',
            'deliveredOrders',
            'latestOrders',
            'currentDeliveries',
        ));
    }
}
