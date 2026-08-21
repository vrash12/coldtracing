<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TelemetryLog;
use App\Models\Trip;
use App\Models\User;
use App\Services\ColdChain\RouteRecommendationScoringService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RouteRecommendationScoringServiceTest extends TestCase
{
    public function test_it_discards_client_scores_and_uses_database_owned_cargo_data(): void
    {
        $order = $this->makeOrder();
        $service = new RouteRecommendationScoringService;

        $result = $service->score($order, 7, [
            [
                'route_id' => 'slow_route',
                'original_index' => 0,
                'eta_minutes' => 60,
                'distance_km' => 20,
                'temperature_risk' => 0,
                'rsl_risk' => 0,
                'route_score' => 0,
            ],
            [
                'route_id' => 'fast_route',
                'original_index' => 1,
                'eta_minutes' => 30,
                'distance_km' => 15,
                'temperature_risk' => 1,
                'rsl_risk' => 1,
                'route_score' => 999,
            ],
        ]);

        $this->assertSame('fast_route', $result['lowest_score_route_id']);
        $this->assertSame(12.0, $result['server_verified_condition']['temperature']);
        $this->assertSame('ESP32-CT-1003', $result['server_verified_condition']['device_code']);

        $fastRoute = collect($result['route_options'])->firstWhere('route_id', 'fast_route');
        $slowRoute = collect($result['route_options'])->firstWhere('route_id', 'slow_route');

        $this->assertSame(1.0, $fastRoute['temperature_risk']);
        $this->assertSame('High', $fastRoute['temperature_risk_label']);
        $this->assertSame(79.5, $fastRoute['projected_rsl_hours_at_arrival']);
        $this->assertLessThan($slowRoute['route_score'], $fastRoute['route_score']);
        $this->assertNotSame(999.0, $fastRoute['route_score']);
    }

    public function test_it_rejects_telemetry_from_a_device_assigned_to_another_truck(): void
    {
        $order = $this->makeOrder(deviceTruckId: 99);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('latest telemetry device');

        (new RouteRecommendationScoringService)->score($order, 7, [[
            'route_id' => 'route_1',
            'original_index' => 0,
            'eta_minutes' => 30,
            'distance_km' => 10,
        ]]);
    }

    private function makeOrder(int $deviceTruckId = 3): Order
    {
        $receiver = (new User)->forceFill([
            'id' => 8,
            'name' => 'Receiver One',
        ]);
        $product = (new Product)->forceFill([
            'id' => 4,
            'name' => 'Vaccines',
            'min_temp' => 2,
            'max_temp' => 8,
            'initial_shelf_life_hours' => 100,
        ]);
        $device = (new Device)->forceFill([
            'id' => 5,
            'truck_id' => $deviceTruckId,
            'device_code' => 'ESP32-CT-1003',
        ]);
        $telemetry = (new TelemetryLog)->forceFill([
            'id' => 6,
            'trip_id' => 9,
            'device_id' => 5,
            'temperature' => 12,
            'rsl_hours' => 80,
            'recorded_at' => Carbon::parse('2026-08-12 10:00:00'),
        ]);
        $telemetry->setRelation('device', $device);

        $trip = (new Trip)->forceFill([
            'id' => 9,
            'order_id' => 10,
            'truck_id' => 3,
            'product_id' => 4,
            'driver_id' => 7,
        ]);
        $trip->setRelation('product', $product);
        $trip->setRelation('latestTelemetry', $telemetry);

        $item = (new OrderItem)->forceFill([
            'id' => 11,
            'order_id' => 10,
            'product_id' => 4,
        ]);
        $item->setRelation('product', $product);

        $order = (new Order)->forceFill([
            'id' => 10,
            'order_code' => 'ORD-TEST-001',
            'driver_id' => 7,
            'receiver_id' => 8,
            'delivery_address' => 'Quezon City',
            'status' => 'assigned',
        ]);
        $order->setRelation('receiver', $receiver);
        $order->setRelation('orderItems', new Collection([$item]));
        $order->setRelation('trip', $trip);

        return $order;
    }
}
