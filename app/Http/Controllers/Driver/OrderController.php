<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Order;
use App\Models\Trip;
use App\Services\ColdChain\RouteRecommendationScoringService;
use App\Services\ColdChain\SimulatedTemperatureService;
use App\Services\ColdChain\TelemetryProcessingService;
use App\Services\ColdChain\TemperatureStatusService;
use App\Services\OpenAIRouteRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    private const MAX_GPS_AGE_SECONDS = 120;

    /**
     * Orders with these statuses are included in the driver's optimized route.
     * Delivered and cancelled orders are not included because they no longer need navigation.
     */
    private const NAVIGABLE_ORDER_STATUSES = [
        'pending',
        'approved',
        'assigned',
        'in_transit',
    ];

    private function authorizeDriver(): void
    {
        if (! Auth::check() || ! Auth::user()->isDriver()) {
            abort(403, 'Only drivers can access driver orders.');
        }
    }

    private function authorizeDriverOrder(Order $order): void
    {
        $this->authorizeDriver();

        if ((int) $order->driver_id !== (int) Auth::id()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeDriver();

        $driver = Auth::user();
        $driverId = (int) $driver->id;

        $search = $request->input('search');
        $status = $request->input('status');

        /**
         * This paginated query is only for the visible order list/table.
         * The optimized route below uses ALL active assigned orders, not only the current page.
         */
        $orders = Order::with([
            'receiver',
            'creator',
            'orderItems.product',
        ])
            ->where('driver_id', $driverId)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('order_code', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%")
                        ->orWhereHas('receiver', function ($receiverQuery) use ($search) {
                            $receiverQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('orderItems.product', function ($productQuery) use ($search) {
                            $productQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        /**
         * Route planner data: all active assigned orders with coordinates.
         * This is intentionally separate from pagination and search filters.
         */
        $routeOrders = $this->getNavigableOrdersForDriver($driverId);
        $routeStops = $this->buildRouteStops($routeOrders);
        $ordersMissingCoordinates = $this->getNavigableOrdersMissingCoordinates($driverId);

        /**
         * Current truck/device GPS comes from the latest telemetry trip for this driver.
         * If this is unavailable, the Blade page can also use browser GPS as a fallback.
         */
        $telemetryTrip = $this->findLatestTelemetryTripForDriver();
        $latestTelemetry = $telemetryTrip?->latestTelemetry;
        $gpsState = $this->freshGpsState($telemetryTrip);
        $hasCurrentGps = $gpsState['available'];
        $currentLat = $gpsState['latitude'];
        $currentLng = $gpsState['longitude'];
        $gpsAgeSeconds = $gpsState['age_seconds'];
        $gpsSource = $gpsState['source'];

        /*
         * Prefer the device paired with this driver's own truck. Deriving it
         * from past telemetry instead would leave the filter empty until the
         * first reading arrives, and an empty filter accepts whatever the
         * wildcard topic carries, including another truck's position.
         */
        $driverDevice = $driver->assignedTruck?->devices()->orderBy('device_code')->first()
            ?? $latestTelemetry?->device;

        $expectedDeviceCode = $driverDevice?->device_code;
        $expectedTopic = $driverDevice?->mqtt_topic ?? 'coldtrace/trucks/+/telemetry';

        $googleMapsApiKey = config('services.google_maps.key', env('GOOGLE_MAPS_API_KEY'));

        /**
         * Recommended .env keys:
         * HIVEMQ_WEBSOCKET_URL=wss://your-cluster.s1.eu.hivemq.cloud:8884/mqtt
         * HIVEMQ_USERNAME=your_limited_websocket_user
         * HIVEMQ_PASSWORD=your_limited_websocket_password
         *
         * Use a limited MQTT user because these values are sent to the browser for live telemetry.
         */
        $mqttBroker = config('services.hivemq.websocket_url', env('HIVEMQ_WEBSOCKET_URL', ''));
        $mqttUsername = config('services.hivemq.username', env('HIVEMQ_USERNAME'));
        $mqttPassword = config('services.hivemq.password', env('HIVEMQ_PASSWORD'));

        return view('driver.orders.index', compact(
            'driver',
            'orders',
            'search',
            'status',
            'routeStops',
            'ordersMissingCoordinates',
            'telemetryTrip',
            'latestTelemetry',
            'hasCurrentGps',
            'currentLat',
            'currentLng',
            'gpsAgeSeconds',
            'gpsSource',
            'expectedDeviceCode',
            'expectedTopic',
            'googleMapsApiKey',
            'mqttBroker',
            'mqttUsername',
            'mqttPassword'
        ));
    }

    public function show(
        Order $order,
        TemperatureStatusService $temperatureStatusService
    ) {
        $this->authorizeDriverOrder($order);

        $order->load([
            'receiver',
            'creator',
            'orderItems.product',
        ]);

        $telemetryTrip = $this->findTelemetryTripForOrder($order)
            ?: $this->findLatestTelemetryTripForDriver();

        $latestTelemetry = $telemetryTrip?->latestTelemetry;

        $monitoredProduct = $telemetryTrip?->product
            ?: $order->orderItems->first()?->product;

        $temperatureState = $temperatureStatusService->evaluate(
            $latestTelemetry?->temperature,
            $monitoredProduct
        );
        $temperatureStatus = $temperatureState['label'];
        $temperatureClass = $temperatureState['class'];

        $gpsState = $this->freshGpsState($telemetryTrip);
        $hasCurrentGps = $gpsState['available'];
        $currentLat = $gpsState['latitude'];
        $currentLng = $gpsState['longitude'];
        $gpsAgeSeconds = $gpsState['age_seconds'];
        $gpsSource = $gpsState['source'];

        $destination = $order->delivery_lat && $order->delivery_lng
            ? $order->delivery_lat.','.$order->delivery_lng
            : $order->delivery_address;

        /**
         * Single-order fallback map URLs. Pickup is intentionally not used.
         * The all-order optimized route is now on driver.orders.index.
         */
        $mapsUrl = $destination
            ? 'https://www.google.com/maps/dir/?api=1'
                .'&destination='.urlencode($destination)
                .'&travelmode=driving'
            : null;

        $currentToDeliveryMapsUrl = $hasCurrentGps && $destination
            ? 'https://www.google.com/maps/dir/?api=1'
                .'&origin='.urlencode($currentLat.','.$currentLng)
                .'&destination='.urlencode($destination)
                .'&travelmode=driving'
            : $mapsUrl;

        $googleMapsApiKey = config('services.google_maps.key', env('GOOGLE_MAPS_API_KEY'));

        return view('driver.orders.show', compact(
            'order',
            'telemetryTrip',
            'latestTelemetry',
            'monitoredProduct',
            'temperatureStatus',
            'temperatureClass',
            'hasCurrentGps',
            'currentLat',
            'currentLng',
            'gpsAgeSeconds',
            'gpsSource',
            'mapsUrl',
            'currentToDeliveryMapsUrl',
            'googleMapsApiKey'
        ));
    }

    public function simulateTelemetry(
        Request $request,
        SimulatedTemperatureService $temperatureSimulator,
        TelemetryProcessingService $telemetryProcessor,
        TemperatureStatusService $temperatureStatusService
    ): JsonResponse {
        $this->authorizeDriver();

        $validated = $request->validate([
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $trip = Trip::query()
            ->with(['product', 'latestTelemetry', 'truck'])
            ->where('driver_id', Auth::id())
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest('started_at')
            ->latest('id')
            ->first();

        if (! $trip || ! $trip->truck) {
            return response()->json([
                'success' => false,
                'message' => 'No active trip and truck are assigned to this driver.',
            ], 422);
        }

        $accuracy = isset($validated['accuracy_meters'])
            ? (float) $validated['accuracy_meters']
            : null;
        $locationAccepted = isset($validated['latitude'], $validated['longitude'])
            && $accuracy !== null
            && $accuracy > 0
            && $accuracy <= 200;

        $device = Device::query()->firstOrCreate(
            ['device_code' => 'SIM-TRUCK-'.$trip->truck_id],
            [
                'truck_id' => $trip->truck_id,
                'mqtt_topic' => 'coldtrace/simulator/trucks/'.$trip->truck_id.'/telemetry',
                'status' => 'active',
            ]
        );

        if ((int) $device->truck_id !== (int) $trip->truck_id) {
            $device->update(['truck_id' => $trip->truck_id]);
        }

        $telemetry = $telemetryProcessor->process([
            'device_code' => $device->device_code,
            'temperature' => $temperatureSimulator->nextForTrip($trip),
            'latitude' => $locationAccepted ? (float) $validated['latitude'] : null,
            'longitude' => $locationAccepted ? (float) $validated['longitude'] : null,
            'recorded_at' => now()->toIso8601String(),
        ]);
        $temperatureState = $temperatureStatusService->evaluate(
            $telemetry->temperature,
            $trip->product
        );

        return response()->json([
            'success' => true,
            'message' => 'Software telemetry generated.',
            'data' => [
                'source' => 'simulated_temperature',
                'temperature' => (float) $telemetry->temperature,
                'temperature_status' => $temperatureState['label'],
                'latitude' => $telemetry->latitude !== null ? (float) $telemetry->latitude : null,
                'longitude' => $telemetry->longitude !== null ? (float) $telemetry->longitude : null,
                'location_accepted' => $locationAccepted,
                'accuracy_meters' => $accuracy,
                'mkt_value' => $telemetry->mkt_value !== null ? (float) $telemetry->mkt_value : null,
                'rsl_hours' => $telemetry->rsl_hours !== null ? (float) $telemetry->rsl_hours : null,
                'recorded_at' => $telemetry->recorded_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function aiRouteRecommendation(
        Request $request,
        Order $order,
        RouteRecommendationScoringService $scoring,
        OpenAIRouteRecommendationService $openAi
    ): JsonResponse {
        $this->authorizeDriverOrder($order);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'route_options' => ['required', 'array', 'min:1', 'max:5'],
            'route_options.*.route_id' => ['required', 'string', 'max:50', 'distinct:strict'],
            'route_options.*.original_index' => ['nullable', 'integer', 'min:0'],
            'route_options.*.eta_minutes' => ['required', 'numeric', 'gt:0', 'max:1440'],
            'route_options.*.distance_km' => ['required', 'numeric', 'gt:0', 'max:10000'],
        ]);

        if ((int) $validated['order_id'] !== (int) $order->id) {
            abort(403, 'Invalid order request.');
        }

        $serverPayload = $scoring->score(
            $order,
            (int) Auth::id(),
            $validated['route_options'],
        );
        $recommendation = $openAi->recommend($serverPayload);

        return response()->json([
            'success' => true,
            'recommendation' => $recommendation,
            'server_scoring' => [
                'route_options' => $serverPayload['route_options'],
                'lowest_score_route_id' => $serverPayload['lowest_score_route_id'],
                'telemetry' => $serverPayload['server_verified_condition'],
            ],
        ]);
    }

    private function getNavigableOrdersForDriver(int $driverId)
    {
        return Order::with([
            'receiver',
            'orderItems.product',
        ])
            ->where('driver_id', $driverId)
            ->whereIn('status', self::NAVIGABLE_ORDER_STATUSES)
            ->whereNotNull('delivery_lat')
            ->whereNotNull('delivery_lng')
            ->orderByRaw('expected_delivery_at IS NULL')
            ->orderBy('expected_delivery_at')
            ->orderByDesc('created_at')
            ->get();
    }

    private function getNavigableOrdersMissingCoordinates(int $driverId)
    {
        return Order::with(['receiver'])
            ->where('driver_id', $driverId)
            ->whereIn('status', self::NAVIGABLE_ORDER_STATUSES)
            ->where(function ($query) {
                $query->whereNull('delivery_lat')
                    ->orWhereNull('delivery_lng');
            })
            ->orderByRaw('expected_delivery_at IS NULL')
            ->orderBy('expected_delivery_at')
            ->orderByDesc('created_at')
            ->get();
    }

    private function buildRouteStops($routeOrders)
    {
        return $routeOrders
            ->map(function (Order $routeOrder) {
                $products = $routeOrder->orderItems
                    ->map(function ($item) {
                        $productName = $item->product?->name ?? 'N/A';
                        $quantity = number_format((float) $item->quantity, 2);
                        $unit = $item->unit ?: '';

                        return trim("{$productName} - {$quantity} {$unit}");
                    })
                    ->values()
                    ->all();

                return [
                    'id' => (int) $routeOrder->id,
                    'order_code' => $routeOrder->order_code,
                    'status' => $routeOrder->status,
                    'receiver_name' => $routeOrder->receiver?->name ?? 'N/A',
                    'receiver_phone' => $routeOrder->receiver?->phone,
                    'receiver_email' => $routeOrder->receiver?->email,
                    'address' => $routeOrder->delivery_address,
                    'lat' => (float) $routeOrder->delivery_lat,
                    'lng' => (float) $routeOrder->delivery_lng,
                    'expected_delivery_at' => $routeOrder->expected_delivery_at?->format('M d, Y h:i A'),
                    'products' => $products,
                    'url' => route('driver.orders.show', $routeOrder),
                ];
            })
            ->values();
    }

    private function findTelemetryTripForOrder(Order $order): ?Trip
    {
        $query = Trip::with([
            'truck',
            'product',
            'receiver',
            'latestTelemetry.device',
            'latestGpsTelemetry.device',
        ])
            ->where('driver_id', Auth::id());

        if (Schema::hasColumn('trips', 'order_id')) {
            $trip = (clone $query)
                ->where('order_id', $order->id)
                ->latest()
                ->first();

            if ($trip) {
                return $trip;
            }
        }

        return $query
            ->where('receiver_id', $order->receiver_id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where(function ($subQuery) use ($order) {
                $subQuery->where('destination_address', $order->delivery_address);

                if ($order->delivery_lat && $order->delivery_lng) {
                    $subQuery->orWhere(function ($locationQuery) use ($order) {
                        $locationQuery
                            ->where('destination_lat', $order->delivery_lat)
                            ->where('destination_lng', $order->delivery_lng);
                    });
                }
            })
            ->latest()
            ->first();
    }

    private function findLatestTelemetryTripForDriver(): ?Trip
    {
        return Trip::with([
            'truck',
            'product',
            'receiver',
            'latestTelemetry.device',
            'latestGpsTelemetry.device',
        ])
            ->where('driver_id', Auth::id())
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('latestTelemetry')
            ->latest()
            ->first();
    }

    /**
     * Use only a recent, complete device fix for routing. A temperature-only
     * reading must not erase the last valid position, while an old position
     * must not be presented as the truck's current location.
     *
     * @return array{available: bool, latitude: ?float, longitude: ?float, age_seconds: ?int, source: ?string}
     */
    private function freshGpsState(?Trip $trip): array
    {
        $gpsTelemetry = $trip?->latestGpsTelemetry;
        $recordedAt = $gpsTelemetry?->recorded_at;
        $ageSeconds = $recordedAt
            ? (int) abs(now()->diffInSeconds($recordedAt))
            : null;
        $coordinatesAreValid = $gpsTelemetry
            && $gpsTelemetry->latitude !== null
            && $gpsTelemetry->longitude !== null
            && is_finite((float) $gpsTelemetry->latitude)
            && is_finite((float) $gpsTelemetry->longitude)
            && (float) $gpsTelemetry->latitude >= -90
            && (float) $gpsTelemetry->latitude <= 90
            && (float) $gpsTelemetry->longitude >= -180
            && (float) $gpsTelemetry->longitude <= 180
            && ! (
                (float) $gpsTelemetry->latitude === 0.0
                && (float) $gpsTelemetry->longitude === 0.0
            );
        $available = $coordinatesAreValid
            && $ageSeconds !== null
            && $ageSeconds <= self::MAX_GPS_AGE_SECONDS;

        return [
            'available' => $available,
            'latitude' => $available ? (float) $gpsTelemetry->latitude : null,
            'longitude' => $available ? (float) $gpsTelemetry->longitude : null,
            'age_seconds' => $ageSeconds,
            'source' => $available && str_starts_with((string) $gpsTelemetry->device?->device_code, 'SIM-')
                ? 'software'
                : ($available ? 'esp32' : null),
        ];
    }

    public function latestTelemetry(
        Order $order,
        TemperatureStatusService $temperatureStatusService
    ): JsonResponse {
        $this->authorizeDriverOrder($order);

        $trip = Trip::query()
            ->with([
                'latestTelemetry.device',
                'latestGpsTelemetry.device',
                'product',
            ])
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        if (! $trip) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No trip is linked to this order.',
            ]);
        }

        $latest = $trip->latestTelemetry;

        if (! $latest) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No telemetry reading is available yet.',
            ]);
        }

        $temperatureState = $temperatureStatusService->evaluate(
            $latest->temperature,
            $trip->product
        );
        $gpsState = $this->freshGpsState($trip);
        $temperatureSource = str_starts_with(
            (string) $latest->device?->device_code,
            'SIM-'
        ) ? 'simulated' : 'sensor';

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'device_code' => $latest->device?->device_code,
                'temperature_source' => $temperatureSource,
                'temperature' => $latest->temperature !== null
                    ? (float) $latest->temperature
                    : null,
                'humidity' => $latest->humidity !== null
                    ? (float) $latest->humidity
                    : null,
                'latitude' => $gpsState['latitude'],
                'longitude' => $gpsState['longitude'],
                'location_source' => $gpsState['source'],
                'gps_age_seconds' => $gpsState['age_seconds'],
                'mkt_value' => $latest->mkt_value !== null
                    ? (float) $latest->mkt_value
                    : null,
                'rsl_hours' => $latest->rsl_hours !== null
                    ? (float) $latest->rsl_hours
                    : null,
                'temperature_status' => $temperatureState['label'],
                'temperature_status_code' => $temperatureState['code'],
                'temperature_class' => $temperatureState['class'],
                'recorded_at' => $latest->recorded_at?->toIso8601String(),
            ],
        ]);
    }
}
