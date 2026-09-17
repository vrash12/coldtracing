<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use App\Notifications\TemperatureAlertNotification;
use App\Services\ColdChain\MktCalculatorService;
use Database\Seeders\ColdTraceDeviceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_esp32_gps_and_temperature_are_stored_processed_and_returned_to_the_driver(): void
    {
        $context = $this->createActiveDeliveryContext();

        config()->set(
            'coldtrace.telemetry.token',
            'test-device-token'
        );

        $response = $this
            ->withHeader('X-ColdTrace-Token', 'test-device-token')
            ->postJson('/api/telemetry', [
                'device_code' => $context['device']->device_code,
                'temperature' => 4.5,
                'latitude' => 14.6760000,
                'longitude' => 121.0437000,

                // The generated sketch may send these MQTT-oriented fields.
                // Laravel intentionally stores only its validated fields.
                'gps_valid' => true,
                'satellites' => 8,
                'speed_kmph' => 21.4,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.trip_id', $context['trip']->id)
            ->assertJsonPath('data.device_id', $context['device']->id)
            ->assertJsonPath('data.temperature', 4.5)
            ->assertJsonPath('data.latitude', 14.676)
            ->assertJsonPath('data.longitude', 121.0437)
            ->assertJsonPath('data.mkt_value', 4.5)
            ->assertJsonPath('data.temperature_status', 'Safe')
            ->assertJsonPath('data.temperature_status_code', 'safe');

        $telemetryId = $response->json('data.id');

        $this->assertDatabaseHas('telemetry_logs', [
            'id' => $telemetryId,
            'trip_id' => $context['trip']->id,
            'device_id' => $context['device']->id,
            'temperature' => 4.5,
            'latitude' => 14.6760000,
            'longitude' => 121.0437000,
        ]);

        $latestTelemetry = $context['trip']
            ->fresh()
            ->latestTelemetry()
            ->firstOrFail();

        $this->assertSame(4.5, (float) $latestTelemetry->mkt_value);
        $this->assertNotNull($latestTelemetry->rsl_hours);
        $this->assertLessThan(
            (float) $context['product']->initial_shelf_life_hours,
            (float) $latestTelemetry->rsl_hours
        );

        $context['device']->refresh();

        $this->assertSame('active', $context['device']->status);
        $this->assertNotNull($context['device']->last_seen_at);

        $this
            ->actingAs($context['driver'])
            ->getJson(route(
                'driver.orders.telemetry.latest',
                $context['order']
            ))
            ->assertOk()
            ->assertJsonPath('data.temperature', 4.5)
            ->assertJsonPath('data.latitude', 14.676)
            ->assertJsonPath('data.longitude', 121.0437)
            ->assertJsonPath('data.mkt_value', 4.5)
            ->assertJsonPath('data.temperature_status', 'Safe')
            ->assertJsonPath('data.temperature_class', 'safe');
    }

    public function test_telemetry_requires_the_configured_device_token(): void
    {
        $context = $this->createActiveDeliveryContext();

        config()->set(
            'coldtrace.telemetry.token',
            'test-device-token'
        );

        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 4.5,
            'latitude' => 14.676,
            'longitude' => 121.0437,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('telemetry_logs', 0);
    }

    public function test_gps_coordinates_must_be_submitted_as_a_complete_pair(): void
    {
        $context = $this->createActiveDeliveryContext();

        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 4.5,
            'latitude' => 14.676,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->assertDatabaseCount('telemetry_logs', 0);
    }

    public function test_low_quality_device_gps_is_not_stored_as_a_current_position(): void
    {
        $context = $this->createActiveDeliveryContext();

        $response = $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 4.5,
            'latitude' => 16.6900000,
            'longitude' => 121.5500000,
            'gps_valid' => false,
            'satellites' => 2,
            'hdop' => 18.4,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.latitude', null)
            ->assertJsonPath('data.longitude', null);

        $this->assertDatabaseHas('telemetry_logs', [
            'id' => $response->json('data.id'),
            'latitude' => null,
            'longitude' => null,
            'temperature' => 4.5,
        ]);
    }

    public function test_saved_gps_expires_without_a_new_fix_and_temperature_updates_do_not_extend_it(): void
    {
        $context = $this->createActiveDeliveryContext();
        $this->freezeTime();
        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 4.5, 'latitude' => 14.676, 'longitude' => 121.0437,
            'gps_valid' => true, 'satellites' => 8, 'hdop' => 1,
        ])->assertCreated();
        $recordedAt = now()->toIso8601String();
        $this->travel(20)->seconds();
        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code, 'temperature' => 4.6,
        ])->assertCreated();
        $this->actingAs($context['driver'])->getJson(route('driver.orders.telemetry.latest', $context['order']))
            ->assertOk()->assertJsonPath('data.latitude', 14.676)
            ->assertJsonPath('data.gps_recorded_at', $recordedAt);
        $this->travel(10)->seconds();
        $this->getJson(route('driver.orders.telemetry.latest', $context['order']))
            ->assertOk()->assertJsonPath('data.latitude', null)->assertJsonPath('data.longitude', null)
            ->assertJsonPath('data.temperature', 4.6);
        $this->get(route('driver.orders.show', $context['order']))->assertOk()->assertViewHas('hasCurrentGps', false);
    }

    public function test_closed_trip_keeps_its_history_without_a_live_marker(): void
    {
        $context = $this->createActiveDeliveryContext();
        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 4.5, 'latitude' => 14.676, 'longitude' => 121.0437,
            'gps_valid' => true,
        ])->assertCreated();
        $context['trip']->update(['status' => 'completed']);
        $this->actingAs($context['driver'])->getJson(route('driver.orders.telemetry.latest', $context['order']))
            ->assertOk()->assertJsonPath('data.trip_status', 'completed')
            ->assertJsonPath('data.latitude', null)->assertJsonPath('data.temperature', 4.5);
        $this->get(route('driver.orders.show', $context['order']))->assertOk()->assertViewHas('hasCurrentGps', false);
    }

    public function test_driver_can_generate_continuous_software_telemetry_with_accurate_browser_location(): void
    {
        $context = $this->createActiveDeliveryContext();

        $response = $this
            ->actingAs($context['driver'])
            ->postJson(route('driver.telemetry.software-feed'), [
                'latitude' => 14.6760000,
                'longitude' => 121.0437000,
                'accuracy_meters' => 24.5,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'simulated_temperature')
            ->assertJsonPath('data.location_accepted', true)
            ->assertJsonPath('data.latitude', 14.676)
            ->assertJsonPath('data.longitude', 121.0437);

        $this->assertIsNumeric($response->json('data.temperature'));
        $this->assertDatabaseHas('devices', [
            'truck_id' => $context['truck']->id,
            'device_code' => 'SIM-TRUCK-'.$context['truck']->id,
        ]);
        $this->assertDatabaseHas('telemetry_logs', [
            'trip_id' => $context['trip']->id,
            'latitude' => 14.6760000,
            'longitude' => 121.0437000,
        ]);
    }

    public function test_trip_mkt_uses_irregular_timestamps_and_the_product_activation_energy(): void
    {
        $context = $this->createActiveDeliveryContext();
        $context['product']->update([
            'activation_energy_j_per_mol' => 50000,
        ]);

        $latestRecordedAt = now()->startOfSecond();

        $readings = [
            [
                'temperature' => 2.0,
                'recorded_at' => $latestRecordedAt
                    ->copy()
                    ->subMinutes(40)
                    ->toIso8601String(),
            ],
            [
                'temperature' => 6.0,
                'recorded_at' => $latestRecordedAt
                    ->copy()
                    ->subMinutes(30)
                    ->toIso8601String(),
            ],
            [
                'temperature' => 12.0,
                'recorded_at' => $latestRecordedAt->toIso8601String(),
            ],
        ];

        foreach ($readings as $reading) {
            $response = $this->postJson('/api/telemetry', [
                'device_code' => $context['device']->device_code,
                ...$reading,
            ])->assertCreated();
        }

        $expected = app(MktCalculatorService::class)
            ->calculateWeighted(
                readings: [
                    [
                        'temperature' => 2.0,
                        'duration_seconds' => 300,
                    ],
                    [
                        'temperature' => 6.0,
                        'duration_seconds' => 1200,
                    ],
                    [
                        'temperature' => 12.0,
                        'duration_seconds' => 900,
                    ],
                ],
                activationEnergyJPerMol: 50000.0
            );

        $this->assertSame(
            $expected,
            (float) $response->json('data.mkt_value')
        );
        $this->assertNotSame(
            app(MktCalculatorService::class)->calculateWeighted([
                [
                    'temperature' => 2.0,
                    'duration_seconds' => 300,
                ],
                [
                    'temperature' => 6.0,
                    'duration_seconds' => 1200,
                ],
                [
                    'temperature' => 12.0,
                    'duration_seconds' => 900,
                ],
            ]),
            (float) $response->json('data.mkt_value')
        );
    }

    public function test_incomplete_product_profile_keeps_telemetry_and_mkt_without_estimating_rsl(): void
    {
        $context = $this->createActiveDeliveryContext();
        $context['product']->update([
            'reference_storage_temp_celsius' => null,
            'activation_energy_j_per_mol' => null,
        ]);

        $response = $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 4.5,
            'latitude' => 14.676,
            'longitude' => 121.0437,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.mkt_value', 4.5)
            ->assertJsonPath('data.rsl_hours', null);

        $this->assertDatabaseHas('telemetry_logs', [
            'id' => $response->json('data.id'),
            'temperature' => 4.5,
            'mkt_value' => 4.5,
            'rsl_hours' => null,
        ]);
    }

    public function test_temperature_alerts_are_created_deduplicated_resolved_and_sent(): void
    {
        $context = $this->createActiveDeliveryContext();

        $firstHigh = $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 12,
        ]);

        $firstHigh
            ->assertCreated()
            ->assertJsonPath('data.temperature_status', 'Too High')
            ->assertJsonPath('data.temperature_status_code', 'too_high');

        $highAlert = Alert::query()->firstOrFail();

        $this->assertSame('temperature_too_high', $highAlert->type);
        $this->assertSame('critical', $highAlert->severity);
        $this->assertFalse($highAlert->is_resolved);
        $this->assertSame(
            $firstHigh->json('data.id'),
            $highAlert->telemetry_log_id
        );
        $this->assertSame(
            1,
            $context['driver']->notifications()
                ->where('type', TemperatureAlertNotification::class)
                ->count()
        );
        $this->assertSame(
            1,
            $context['receiver']->notifications()
                ->where('type', TemperatureAlertNotification::class)
                ->count()
        );

        $secondHigh = $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 11,
        ])->assertCreated();

        $this->assertDatabaseCount('alerts', 1);
        $this->assertSame(
            $secondHigh->json('data.id'),
            $highAlert->fresh()->telemetry_log_id
        );
        $this->assertSame(
            1,
            $context['driver']->notifications()
                ->where('type', TemperatureAlertNotification::class)
                ->count()
        );

        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.temperature_status', 'Safe');

        $highAlert->refresh();

        $this->assertTrue($highAlert->is_resolved);
        $this->assertNotNull($highAlert->resolved_at);
        $this->assertSame(
            2,
            $context['driver']->notifications()
                ->where('type', TemperatureAlertNotification::class)
                ->count()
        );
        $this->assertContains(
            'resolved',
            $context['driver']->notifications()
                ->where('type', TemperatureAlertNotification::class)
                ->get()
                ->pluck('data.event')
                ->all()
        );

        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.temperature_status', 'Too Low');

        $lowAlert = Alert::query()
            ->where('type', 'temperature_too_low')
            ->firstOrFail();

        $this->assertSame('warning', $lowAlert->severity);
        $this->assertFalse($lowAlert->is_resolved);
        $this->assertDatabaseCount('alerts', 2);
        $this->assertSame(
            3,
            $context['driver']->notifications()
                ->where('type', TemperatureAlertNotification::class)
                ->count()
        );

        $this->postJson('/api/telemetry', [
            'device_code' => $context['device']->device_code,
            'temperature' => 5,
        ])->assertCreated();

        $this->assertTrue($lowAlert->fresh()->is_resolved);
        $this->assertDatabaseMissing('alerts', [
            'trip_id' => $context['trip']->id,
            'is_resolved' => false,
        ]);
        $this->assertSame(
            4,
            $context['driver']->notifications()
                ->where('type', TemperatureAlertNotification::class)
                ->count()
        );
    }

    /**
     * @return array{
     *     driver: User,
     *     receiver: User,
     *     truck: Truck,
     *     device: Device,
     *     product: Product,
     *     order: Order,
     *     trip: Trip
     * }
     */
    private function createActiveDeliveryContext(): array
    {
        $driverRole = Role::create([
            'name' => 'Driver',
            'description' => 'ColdTrace delivery driver.',
        ]);

        $receiverRole = Role::create([
            'name' => 'Receiver',
            'description' => 'ColdTrace delivery receiver.',
        ]);

        $driver = User::factory()->create([
            'role_id' => $driverRole->id,
            'status' => 'active',
        ]);

        $receiver = User::factory()->create([
            'role_id' => $receiverRole->id,
            'status' => 'active',
        ]);

        $truck = Truck::create([
            'driver_id' => $driver->id,
            'plate_number' => 'CT-TEST-01',
            'model' => 'ColdTrace Test Truck',
            'status' => 'in_trip',
        ]);

        $this->seed(ColdTraceDeviceSeeder::class);

        $device = Device::where(
            'device_code',
            'ESP32-CT-1001'
        )->firstOrFail();

        $device->update([
            'truck_id' => $truck->id,
        ]);

        $product = Product::create([
            'name' => 'Telemetry Test Product',
            'min_temp' => 2,
            'max_temp' => 8,
            'initial_shelf_life_hours' => 240,
            'reference_storage_temp_celsius' => 4,
            'activation_energy_j_per_mol' => 83144,
        ]);

        $order = Order::create([
            'order_code' => 'ORD-TELEMETRY-001',
            'created_by' => $receiver->id,
            'receiver_id' => $receiver->id,
            'driver_id' => $driver->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit' => 'kg',
            'pickup_address' => 'ColdTrace Warehouse',
            'pickup_lat' => 14.676,
            'pickup_lng' => 121.0437,
            'delivery_address' => 'ColdTrace Test Destination',
            'delivery_lat' => 14.70,
            'delivery_lng' => 121.05,
            'status' => 'in_transit',
        ]);

        $order->orderItems()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit' => 'kg',
        ]);

        $trip = Trip::create([
            'order_id' => $order->id,
            'truck_id' => $truck->id,
            'product_id' => $product->id,
            'driver_id' => $driver->id,
            'receiver_id' => $receiver->id,
            'origin_address' => 'ColdTrace Warehouse',
            'origin_lat' => 14.676,
            'origin_lng' => 121.0437,
            'destination_address' => 'ColdTrace Test Destination',
            'destination_lat' => 14.70,
            'destination_lng' => 121.05,
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);

        return compact(
            'driver',
            'receiver',
            'truck',
            'device',
            'product',
            'order',
            'trip'
        );
    }
}
