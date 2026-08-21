<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TelemetryLog;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    private function authorizeAdmin(): void
    {
        if (!Auth::check() || !Auth::user()->isAdministrator()) {
            abort(403, 'Only administrators can access reports.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        [$from, $to] = $this->resolveDateRange($request);

        $ordersQuery = Order::query()
            ->whereBetween('created_at', [$from, $to]);

        $tripsQuery = Trip::query()
            ->whereBetween('created_at', [$from, $to]);

        $alertsQuery = Alert::query()
            ->whereBetween('created_at', [$from, $to]);

        $telemetryQuery = TelemetryLog::query()
            ->whereBetween('recorded_at', [$from, $to]);

        $summary = [
            'total_orders' => (clone $ordersQuery)->count(),
            'pending_orders' => (clone $ordersQuery)->where('status', 'pending')->count(),
            'active_orders' => (clone $ordersQuery)->whereIn('status', ['assigned', 'in_transit'])->count(),
            'delivered_orders' => (clone $ordersQuery)->where('status', 'delivered')->count(),
            'cancelled_orders' => (clone $ordersQuery)->where('status', 'cancelled')->count(),

            'total_trips' => (clone $tripsQuery)->count(),
            'active_trips' => (clone $tripsQuery)->where('status', 'in_progress')->count(),
            'completed_trips' => (clone $tripsQuery)->where('status', 'completed')->count(),

            'total_alerts' => (clone $alertsQuery)->count(),
            'critical_alerts' => (clone $alertsQuery)
                ->where('severity', 'critical')
                ->where('is_resolved', false)
                ->count(),

            'total_trucks' => Truck::count(),
            'active_devices' => Device::where('status', 'active')->count(),

            'avg_temperature' => round((float) (clone $telemetryQuery)->avg('temperature'), 2),
            'temperature_breaches' => $this->countTemperatureBreaches($from, $to),
        ];

        $ordersByStatus = (clone $ordersQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $tripsByStatus = (clone $tripsQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $alertsBySeverity = (clone $alertsQuery)
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->orderBy('severity')
            ->get();

        $topProducts = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->select(
                'products.name',
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders_count'),
                DB::raw('SUM(order_items.quantity) as total_quantity')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        $driverPerformance = User::query()
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->leftJoin('orders', function ($join) use ($from, $to) {
                $join->on('orders.driver_id', '=', 'users.id')
                    ->whereBetween('orders.created_at', [$from, $to]);
            })
            ->where('roles.name', 'Driver')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw("SUM(CASE WHEN orders.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders"),
                DB::raw("SUM(CASE WHEN orders.status = 'in_transit' THEN 1 ELSE 0 END) as in_transit_orders"),
                DB::raw("SUM(CASE WHEN orders.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders")
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get();

        $recentOrders = Order::with(['receiver', 'driver', 'creator', 'orderItems.product'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->take(10)
            ->get();

        $recentAlerts = Alert::with(['trip.truck', 'trip.product'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->take(10)
            ->get();

        $latestTelemetry = TelemetryLog::with(['trip.truck', 'device'])
            ->whereBetween('recorded_at', [$from, $to])
            ->latest('recorded_at')
            ->take(10)
            ->get();

        $dailyOrderRows = (clone $ordersQuery)
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'report_date');

        $dailyTemperatureRows = (clone $telemetryQuery)
            ->selectRaw('DATE(recorded_at) as report_date, ROUND(AVG(temperature), 2) as average_temperature')
            ->whereNotNull('temperature')
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy(DB::raw('DATE(recorded_at)'))
            ->pluck('average_temperature', 'report_date');

        $dateLabels = [];
        $dailyOrderValues = [];
        $dailyTemperatureValues = [];

        for ($date = $from->copy()->startOfDay(); $date->lte($to->copy()->startOfDay()); $date->addDay()) {
            $key = $date->format('Y-m-d');

            $dateLabels[] = $date->format('M d');
            $dailyOrderValues[] = (int) ($dailyOrderRows[$key] ?? 0);
            $dailyTemperatureValues[] = isset($dailyTemperatureRows[$key])
                ? round((float) $dailyTemperatureRows[$key], 2)
                : 0;
        }

        $chartData = [
            'dailyOrders' => [
                'labels' => $dateLabels,
                'values' => $dailyOrderValues,
            ],

            'dailyTemperature' => [
                'labels' => $dateLabels,
                'values' => $dailyTemperatureValues,
            ],

            'ordersByStatus' => [
                'labels' => $ordersByStatus
                    ->map(fn ($row) => ucfirst(str_replace('_', ' ', $row->status)))
                    ->values(),
                'values' => $ordersByStatus
                    ->map(fn ($row) => (int) $row->total)
                    ->values(),
            ],

            'tripsByStatus' => [
                'labels' => $tripsByStatus
                    ->map(fn ($row) => ucfirst(str_replace('_', ' ', $row->status)))
                    ->values(),
                'values' => $tripsByStatus
                    ->map(fn ($row) => (int) $row->total)
                    ->values(),
            ],

            'alertsBySeverity' => [
                'labels' => $alertsBySeverity
                    ->map(fn ($row) => ucfirst($row->severity))
                    ->values(),
                'values' => $alertsBySeverity
                    ->map(fn ($row) => (int) $row->total)
                    ->values(),
            ],

            'topProducts' => [
                'labels' => $topProducts
                    ->pluck('name')
                    ->values(),
                'values' => $topProducts
                    ->map(fn ($row) => (int) $row->orders_count)
                    ->values(),
            ],

            'driverPerformance' => [
                'labels' => $driverPerformance
                    ->pluck('name')
                    ->values(),
                'totalOrders' => $driverPerformance
                    ->map(fn ($row) => (int) $row->total_orders)
                    ->values(),
                'deliveredOrders' => $driverPerformance
                    ->map(fn ($row) => (int) $row->delivered_orders)
                    ->values(),
            ],
        ];

        return view('admin.reports.index', compact(
            'from',
            'to',
            'summary',
            'ordersByStatus',
            'tripsByStatus',
            'alertsBySeverity',
            'topProducts',
            'driverPerformance',
            'recentOrders',
            'recentAlerts',
            'latestTelemetry',
            'chartData'
        ));
    }

    public function exportCsv(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['required', Rule::in(['orders', 'trips', 'alerts', 'telemetry'])],
        ]);

        [$from, $to] = $this->resolveDateRange($request);
        $type = $validated['type'];

        $filename = 'coldtrace_' . $type . '_report_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($type, $from, $to) {
            $handle = fopen('php://output', 'w');

            if ($type === 'orders') {
                fputcsv($handle, [
                    'Order Code',
                    'Receiver',
                    'Driver',
                    'Products',
                    'Delivery Address',
                    'Expected Delivery',
                    'Status',
                    'Created At',
                ]);

                Order::with(['receiver', 'driver', 'orderItems.product'])
                    ->whereBetween('created_at', [$from, $to])
                    ->latest()
                    ->chunk(200, function ($orders) use ($handle) {
                        foreach ($orders as $order) {
                            fputcsv($handle, [
                                $order->order_code,
                                $order->receiver?->name,
                                $order->driver?->name,
                                $order->orderItems->map(function ($item) {
                                    return ($item->product?->name ?? 'N/A') . ' - ' . $item->quantity . ' ' . $item->unit;
                                })->implode(' | '),
                                $order->delivery_address,
                                optional($order->expected_delivery_at)->format('Y-m-d H:i:s'),
                                $order->status,
                                optional($order->created_at)->format('Y-m-d H:i:s'),
                            ]);
                        }
                    });
            }

            if ($type === 'trips') {
                fputcsv($handle, [
                    'Trip ID',
                    'Order Code',
                    'Truck',
                    'Product',
                    'Driver',
                    'Receiver',
                    'Origin',
                    'Destination',
                    'Status',
                    'Started At',
                    'Completed At',
                ]);

                Trip::with(['order', 'truck', 'product', 'driver', 'receiver'])
                    ->whereBetween('created_at', [$from, $to])
                    ->latest()
                    ->chunk(200, function ($trips) use ($handle) {
                        foreach ($trips as $trip) {
                            fputcsv($handle, [
                                $trip->id,
                                $trip->order?->order_code,
                                $trip->truck?->plate_number,
                                $trip->product?->name,
                                $trip->driver?->name,
                                $trip->receiver?->name,
                                $trip->origin_address,
                                $trip->destination_address,
                                $trip->status,
                                optional($trip->started_at)->format('Y-m-d H:i:s'),
                                optional($trip->completed_at)->format('Y-m-d H:i:s'),
                            ]);
                        }
                    });
            }

            if ($type === 'alerts') {
                fputcsv($handle, [
                    'Alert ID',
                    'Trip ID',
                    'Truck',
                    'Product',
                    'Type',
                    'Severity',
                    'Message',
                    'Resolved',
                    'Resolved At',
                    'Created At',
                ]);

                Alert::with(['trip.truck', 'trip.product'])
                    ->whereBetween('created_at', [$from, $to])
                    ->latest()
                    ->chunk(200, function ($alerts) use ($handle) {
                        foreach ($alerts as $alert) {
                            fputcsv($handle, [
                                $alert->id,
                                $alert->trip_id,
                                $alert->trip?->truck?->plate_number,
                                $alert->trip?->product?->name,
                                $alert->type,
                                $alert->severity,
                                $alert->message,
                                $alert->is_resolved ? 'Yes' : 'No',
                                optional($alert->resolved_at)->format('Y-m-d H:i:s'),
                                optional($alert->created_at)->format('Y-m-d H:i:s'),
                            ]);
                        }
                    });
            }

            if ($type === 'telemetry') {
                fputcsv($handle, [
                    'Telemetry ID',
                    'Trip ID',
                    'Device Code',
                    'Truck',
                    'Latitude',
                    'Longitude',
                    'Temperature',
                    'Humidity',
                    'MKT Value',
                    'RSL Hours',
                    'Recorded At',
                ]);

                TelemetryLog::with(['device', 'trip.truck'])
                    ->whereBetween('recorded_at', [$from, $to])
                    ->latest('recorded_at')
                    ->chunk(200, function ($logs) use ($handle) {
                        foreach ($logs as $log) {
                            fputcsv($handle, [
                                $log->id,
                                $log->trip_id,
                                $log->device?->device_code,
                                $log->trip?->truck?->plate_number,
                                $log->latitude,
                                $log->longitude,
                                $log->temperature,
                                $log->humidity,
                                $log->mkt_value,
                                $log->rsl_hours,
                                optional($log->recorded_at)->format('Y-m-d H:i:s'),
                            ]);
                        }
                    });
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function countTemperatureBreaches(Carbon $from, Carbon $to): int
    {
        return TelemetryLog::query()
            ->join('trips', 'telemetry_logs.trip_id', '=', 'trips.id')
            ->join('products', 'trips.product_id', '=', 'products.id')
            ->whereBetween('telemetry_logs.recorded_at', [$from, $to])
            ->whereNotNull('telemetry_logs.temperature')
            ->where(function ($query) {
                $query->whereColumn('telemetry_logs.temperature', '<', 'products.min_temp')
                    ->orWhereColumn('telemetry_logs.temperature', '>', 'products.max_temp');
            })
            ->count();
    }
}