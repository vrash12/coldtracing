<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\TelemetryLog;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DriverAllOrdersOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config()->set('services.google_maps.key', 'browser-key');
        config()->set('services.google_maps.routes_key', 'server-key');
        config()->set('services.openai.key', 'test-ai-key');
        config()->set('services.openai.model', 'configured-model');
        Http::preventStrayRequests();
    }

    public function test_only_active_drivers_can_plan_and_gps_must_be_fresh(): void
    {
        Http::fake();
        $this->postJson(route('driver.orders.optimize'), $this->input())->assertUnauthorized();
        $admin = $this->user('Administrator');
        $this->actingAs($admin)->postJson(route('driver.orders.optimize'), $this->input())->assertForbidden();
        $driver = $this->user();
        $driver->update(['status' => 'inactive']);
        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())->assertForbidden();
        $driver->update(['status' => 'active']);
        foreach ([now()->subSeconds(30), now()->addSeconds(31)] as $time) {
            $this->actingAs($driver)->postJson(route('driver.orders.optimize'), [
                'origin' => ['lat' => 15.5, 'lng' => 120.5, 'recorded_at' => $time->toIso8601String()],
            ])->assertUnprocessable()->assertJsonValidationErrors('origin');
        }
        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), [
            'origin' => ['lat' => 0, 'lng' => 0, 'recorded_at' => now()->toIso8601String()],
        ])->assertUnprocessable()->assertJsonValidationErrors('origin');
        Http::assertNothingSent();
    }

    public function test_it_uses_only_assigned_database_orders_and_google_routes_then_accepts_a_valid_ai_selection(): void
    {
        $driver = $this->user();
        $order = $this->order($driver, 15.75, 120.75);
        $other = $this->order($this->user(), 17.5, 121.5);
        $this->order($driver, 14, 122)->update(['status' => 'delivered']);
        Http::fake([
            self::GOOGLE => Http::response(['routes' => [$this->route(1, 600), $this->route(1, 720)]]),
            'api.openai.com/*' => Http::response($this->aiResponse('route_2')),
        ]);

        $response = $this->actingAs($driver)->postJson(route('driver.orders.optimize'), [
            ...$this->input(), 'order_ids' => [$other->id], 'route_score' => -1000,
            'destinations' => [['lat' => 80, 'lng' => 90]],
        ])->assertOk()->assertJsonPath('result.recommendation.decision_source', 'openai')
            ->assertJsonPath('result.recommendation.recommended_route_id', 'route_2')
            ->assertJsonPath('result.route.duration', '720s')
            ->assertJsonCount(1, 'result.orderedStops')
            ->assertJsonPath('result.orderedStops.0.id', $order->id);
        $this->assertNotEmpty($response->json('result.warnings'));
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GOOGLE
            && $request->hasHeader('X-Goog-Api-Key', 'server-key')
            && $request['computeAlternativeRoutes'] === true
            && $request['destination']['location']['latLng'] === ['latitude' => 15.75, 'longitude' => 120.75]
            && ! isset($request['intermediates']));
        Http::assertSent(function (Request $request) use ($order): bool {
            if (! str_contains($request->url(), 'api.openai.com')) {
                return false;
            }
            $payload = json_decode($request['input'][1]['content'], true);
            $this->assertSame([$order->id], array_column($payload['server_verified_orders'], 'order_id'));
            $this->assertNull($payload['server_verified_orders'][0]['temperature']);
            $this->assertStringNotContainsString('Private destination', $request['input'][1]['content']);
            $this->assertStringNotContainsString('@example.test', $request['input'][1]['content']);
            $this->assertStringNotContainsString('latitude', $request['input'][1]['content']);

            return count($payload['route_options']) === 2;
        });
        Http::assertSentCount(2);
    }

    public function test_google_service_disabled_is_reported_without_an_ai_call_or_fake_polyline(): void
    {
        $driver = $this->user();
        $this->order($driver);
        Http::fake([self::GOOGLE => Http::response([
            'error' => ['status' => 'PERMISSION_DENIED', 'message' => 'Private provider details and key', 'details' => [['reason' => 'SERVICE_DISABLED']]],
        ], 403)]);

        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertStatus(503)->assertJsonPath('code', 'SERVICE_DISABLED')
            ->assertJsonMissingPath('result.route')
            ->assertDontSee('Private provider details');
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'openai.com'));
    }

    public function test_multi_stop_planning_is_bounded_and_visits_every_order_once(): void
    {
        $driver = $this->user();
        $ids = [];
        for ($index = 0; $index < 12; $index++) {
            $ids[] = $this->order($driver, 15 + $index / 100, 120 + $index / 100)->id;
        }
        config()->set('services.openai.key', null);
        Http::fake(function (Request $request) {
            $this->assertSame(self::GOOGLE, $request->url());
            $this->assertTrue($request['optimizeWaypointOrder']);
            $this->assertFalse($request['computeAlternativeRoutes']);
            $this->assertCount(11, $request['intermediates']);

            return Http::response(['routes' => [$this->route(12, 1200, array_reverse(range(0, 10)))]]);
        });

        $response = $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertOk()->assertJsonPath('result.recommendation.decision_source', 'deterministic_fallback')
            ->assertJsonCount(12, 'result.orderedStops');
        $this->assertEqualsCanonicalizing($ids, array_column($response->json('result.orderedStops'), 'id'));
        Http::assertSentCount(3);
    }

    public function test_a_corrupt_google_waypoint_permutation_is_rejected_before_ai(): void
    {
        $driver = $this->user();
        foreach (range(1, 3) as $index) {
            $this->order($driver, 15 + $index, 120);
        }
        Http::fake([self::GOOGLE => Http::response(['routes' => [$this->route(3, 900, [0, 0])]])]);

        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertStatus(503)->assertJsonPath('code', 'NO_VALID_ROAD_ROUTE')->assertJsonMissingPath('result.route');
        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'openai.com'));
    }

    public function test_google_may_omit_zero_distance_for_deliveries_at_the_same_location(): void
    {
        $driver = $this->user();
        $this->order($driver);
        $this->order($driver);
        config()->set('services.openai.key', null);
        $route = $this->route(2, 600, [0]);
        $route['legs'][0] = ['duration' => '600s', 'distanceMeters' => 6000];
        $route['legs'][1] = ['duration' => '0s'];
        Http::fake([self::GOOGLE => Http::response(['routes' => [$route]])]);

        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertOk()->assertJsonCount(2, 'result.orderedStops')
            ->assertJsonPath('result.route.legs.1.distanceMeters', 0);
    }

    public function test_ai_cannot_choose_a_route_that_google_did_not_supply(): void
    {
        $driver = $this->user();
        $this->order($driver);
        Http::fake([
            self::GOOGLE => Http::response(['routes' => [$this->route(1, 600), $this->route(1, 1200)]]),
            'api.openai.com/*' => Http::response($this->aiResponse('invented_route')),
        ]);

        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertOk()->assertJsonPath('result.recommendation.decision_source', 'deterministic_fallback')
            ->assertJsonPath('result.recommendation.recommended_route_id', 'route_1')
            ->assertJsonPath('result.route.duration', '600s');
    }

    public function test_every_orders_cargo_and_cumulative_arrival_are_scored_without_sharing_rsl_between_products(): void
    {
        $driver = $this->user();
        $order = $this->order($driver);
        $second = $this->order($driver, 15.7, 120.7);
        $product = Product::create(['name' => 'Monitored cargo', 'min_temp' => 2, 'max_temp' => 8, 'initial_shelf_life_hours' => 10]);
        $otherProduct = Product::create(['name' => 'Other cargo', 'min_temp' => -20, 'max_temp' => -10, 'initial_shelf_life_hours' => 24]);
        $order->orderItems()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => 'box']);
        $order->orderItems()->create(['product_id' => $otherProduct->id, 'quantity' => 1, 'unit' => 'box']);
        $truck = Truck::create(['plate_number' => 'TEST-4', 'driver_id' => $driver->id, 'driver_name' => $driver->name, 'status' => 'available']);
        $device = Device::create(['truck_id' => $truck->id, 'device_code' => 'TEST-DEVICE', 'mqtt_topic' => 'test/telemetry', 'status' => 'active']);
        $trip = Trip::create([
            'order_id' => $order->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'product_id' => $product->id,
            'origin_address' => 'Warehouse', 'destination_address' => 'Private destination', 'status' => 'in_progress',
        ]);
        TelemetryLog::create(['trip_id' => $trip->id, 'device_id' => $device->id, 'temperature' => 5, 'rsl_hours' => 0.1, 'recorded_at' => now()]);
        Http::fake([
            self::GOOGLE => Http::response(['routes' => [$this->route(2, 1200, [0])]]),
            'api.openai.com/*' => Http::response($this->aiResponse('route_1')),
        ]);

        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertOk()->assertJsonPath('result.recommendation.risk_level', 'critical');
        Http::assertSent(function (Request $request) use ($order, $second, $product, $otherProduct): bool {
            if (! str_contains($request->url(), 'openai.com')) {
                return false;
            }
            $payload = json_decode($request['input'][1]['content'], true);
            $conditions = collect($payload['server_verified_orders'])->keyBy('order_id');
            $this->assertSame(5, $conditions[$order->id]['temperature']);
            $this->assertNull($conditions[$second->id]['temperature']);
            $products = collect($conditions[$order->id]['products'])->keyBy('product_id');
            $this->assertSame(0.1, $products[$product->id]['rsl_hours']);
            $this->assertNull($products[$otherProduct->id]['rsl_hours']);
            foreach ($payload['route_options'] as $option) {
                $this->assertSame([10, 20], array_column($option['deliveries'], 'arrival_minutes'));
                $this->assertEqualsCanonicalizing([$order->id, $second->id], array_column($option['deliveries'], 'order_id'));
            }

            return true;
        });
    }

    public function test_incomplete_locations_and_excessive_deliveries_do_not_call_external_services(): void
    {
        Http::fake();
        $driver = $this->user();
        $missing = $this->order($driver);
        $missing->update(['delivery_lat' => null]);
        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertUnprocessable()->assertJsonValidationErrors('orders');
        $missing->update(['delivery_lat' => 15]);
        foreach (range(1, 25) as $index) {
            $this->order($driver);
        }
        $this->actingAs($driver)->postJson(route('driver.orders.optimize'), $this->input())
            ->assertUnprocessable()->assertJsonValidationErrors('orders');
        Http::assertNothingSent();
    }

    private function user(string $role = 'Driver'): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
            'status' => 'active',
        ]);
    }

    private function order(User $driver, float $lat = 15.6, float $lng = 120.6): Order
    {
        return Order::create([
            'order_code' => 'TEST-'.fake()->unique()->numerify('######'),
            'driver_id' => $driver->id, 'pickup_address' => 'Private warehouse',
            'delivery_address' => 'Private destination', 'delivery_lat' => $lat,
            'delivery_lng' => $lng, 'status' => 'assigned', 'expected_delivery_at' => now()->addHour(),
        ]);
    }

    private function input(): array
    {
        return ['origin' => ['lat' => 15.5, 'lng' => 120.5, 'recorded_at' => now()->toIso8601String()]];
    }

    private function route(int $stops, int $seconds, array $indices = []): array
    {
        return [
            'duration' => $seconds.'s', 'distanceMeters' => $seconds * 10,
            'polyline' => ['encodedPolyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@'],
            'legs' => array_fill(0, $stops, ['duration' => ($seconds / $stops).'s', 'distanceMeters' => $seconds * 10 / $stops]),
            'optimizedIntermediateWaypointIndex' => $indices,
        ];
    }

    private function aiResponse(string $routeId): array
    {
        return ['output_text' => json_encode([
            'recommended_route_id' => $routeId, 'risk_level' => 'safe',
            'driver_action' => 'Follow the selected road route.', 'reason' => 'This route serves every assigned delivery.',
            'cold_chain_warning' => 'Some cargo readings are unavailable.', 'confidence' => 0.8,
        ])];
    }
}
