<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\TelemetryLog;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use App\Services\ColdChain\TelemetryIngestionService;
use Database\Seeders\ColdTraceDeviceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pins the contract against the JSON the ESP32 sketch actually publishes, so a
 * change on either side that breaks the other is caught here rather than in the
 * field. The payloads below are built exactly as buildTelemetryPayload() emits
 * them, including the fields ColdTrace does not store.
 */
class Esp32PayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sketch_payload_is_accepted_and_fully_processed(): void
    {
        $context = $this->activeDelivery();

        $log = app(TelemetryIngestionService::class)->ingest(
            $this->sketchPayload($context['device']->device_code, [
                'temperature' => 4.50,
                'latitude' => 15.4869000,
                'longitude' => 120.5900000,
            ])
        );

        $this->assertSame($context['trip']->id, $log->trip_id);
        $this->assertSame($context['device']->id, $log->device_id);
        $this->assertSame(4.5, (float) $log->temperature);
        $this->assertSame(15.4869, (float) $log->latitude);
        $this->assertSame(120.59, (float) $log->longitude);

        // The trip's cold-chain figures are recalculated from this reading.
        $this->assertNotNull($log->mkt_value);
        $this->assertNotNull($log->rsl_hours);

        // The device heartbeat is what the admin dashboard counts as reporting.
        $device = $context['device']->fresh();
        $this->assertSame('active', $device->status);
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_a_reading_with_no_probe_still_records_the_trucks_position(): void
    {
        $context = $this->activeDelivery();

        // The sketch publishes "temperature": null when the DS18B20 is
        // unavailable, on purpose, so the truck is not lost from the map.
        $log = app(TelemetryIngestionService::class)->ingest(
            $this->sketchPayload($context['device']->device_code, [
                'temperature' => null,
                'temperature_valid' => false,
                'latitude' => 15.4869000,
                'longitude' => 120.5900000,
            ])
        );

        $this->assertNull($log->temperature);
        $this->assertSame(15.4869, (float) $log->latitude);
        $this->assertSame(120.59, (float) $log->longitude);

        // Without a temperature there is nothing to judge, so no alert opens.
        $this->assertSame(0, Alert::where('trip_id', $context['trip']->id)->count());
        $this->assertNull($log->mkt_value);
    }

    public function test_the_http_endpoint_accepts_the_same_payload(): void
    {
        $context = $this->activeDelivery();
        config()->set('coldtrace.telemetry.token', 'sketch-token');

        $this->withHeader('X-ColdTrace-Token', 'sketch-token')
            ->postJson('/api/telemetry', $this->sketchPayload(
                $context['device']->device_code,
                ['temperature' => 4.50, 'latitude' => 15.4869, 'longitude' => 120.59]
            ))
            ->assertCreated()
            ->assertJsonPath('data.temperature_status', 'Safe');

        $this->withHeader('X-ColdTrace-Token', 'sketch-token')
            ->postJson('/api/telemetry', $this->sketchPayload(
                $context['device']->device_code,
                ['temperature' => null, 'temperature_valid' => false]
            ))
            ->assertCreated()
            ->assertJsonPath('data.temperature', null)
            ->assertJsonPath('data.temperature_status', 'No Data');
    }

    public function test_a_payload_without_a_gps_fix_keeps_the_temperature(): void
    {
        $context = $this->activeDelivery();

        // Before a fix the sketch sends null coordinates and gps_valid false.
        $log = app(TelemetryIngestionService::class)->ingest(
            $this->sketchPayload($context['device']->device_code, [
                'temperature' => 5.25,
                'latitude' => null,
                'longitude' => null,
                'gps_valid' => false,
                'satellites' => 0,
            ])
        );

        $this->assertSame(5.25, (float) $log->temperature);
        $this->assertNull($log->latitude);
        $this->assertNull($log->longitude);
    }

    public function test_a_weak_fix_is_discarded_rather_than_mapped(): void
    {
        $context = $this->activeDelivery();

        $log = app(TelemetryIngestionService::class)->ingest(
            $this->sketchPayload($context['device']->device_code, [
                'temperature' => 4.0,
                'latitude' => 15.4869,
                'longitude' => 120.59,
                'gps_valid' => true,
                'satellites' => 2,
            ])
        );

        $this->assertSame(4.0, (float) $log->temperature);
        $this->assertNull($log->latitude, 'A two-satellite fix must not become a map position.');
        $this->assertNull($log->longitude);
    }

    public function test_a_breach_from_the_device_raises_the_alert_the_driver_sees(): void
    {
        $context = $this->activeDelivery();
        $ingestion = app(TelemetryIngestionService::class);

        $ingestion->ingest($this->sketchPayload($context['device']->device_code, [
            'temperature' => 4.0,
        ]));

        $this->travel(15)->minutes();

        $ingestion->ingest($this->sketchPayload($context['device']->device_code, [
            'temperature' => 14.75,
        ]));

        $alert = Alert::where('trip_id', $context['trip']->id)->firstOrFail();
        $this->assertSame('temperature_too_high', $alert->type);
        $this->assertSame('critical', $alert->severity);

        // The driver sees it on their dashboard and trip page.
        $this->actingAs($context['driver'])
            ->get(route('driver.dashboard'))
            ->assertOk()
            ->assertSeeText('14.75');

        $this->actingAs($context['driver'])
            ->get(route('driver.trips.show', $context['trip']))
            ->assertOk()
            ->assertSeeText('Too High');

        // The administrator sees the same reading on live monitoring.
        $this->actingAs($context['administrator'])
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('"temperature":14.75', false);
    }

    public function test_an_unknown_device_code_is_rejected(): void
    {
        $this->activeDelivery();

        $this->expectException(ValidationException::class);

        app(TelemetryIngestionService::class)->ingest(
            $this->sketchPayload('ESP32-CT-9999', ['temperature' => 4.0])
        );
    }

    public function test_a_device_whose_truck_has_no_active_trip_is_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ColdTraceDeviceSeeder::class);

        $truck = Truck::create(['plate_number' => 'IDLE-0001', 'status' => 'available']);
        $device = Device::where('device_code', 'ESP32-CT-1004')->firstOrFail();
        $device->update(['truck_id' => $truck->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No active trip is connected to this device.');

        app(TelemetryIngestionService::class)->ingest(
            $this->sketchPayload($device->device_code, ['temperature' => 4.0])
        );
    }

    public function test_readings_accumulate_into_the_trip_history_the_driver_reads(): void
    {
        $context = $this->activeDelivery();
        $ingestion = app(TelemetryIngestionService::class);

        foreach ([3.8, 4.1, 4.4, 4.2] as $index => $temperature) {
            $ingestion->ingest($this->sketchPayload($context['device']->device_code, [
                'temperature' => $temperature,
                'latitude' => 15.40 + ($index / 100),
                'longitude' => 120.60,
            ]));

            $this->travel(10)->minutes();
        }

        $this->assertSame(4, TelemetryLog::where('trip_id', $context['trip']->id)->count());

        $this->actingAs($context['driver'])
            ->get(route('driver.trips.show', $context['trip']))
            ->assertOk()
            ->assertSeeText('4.20 °C')
            ->assertSeeText('3.80 °C');
    }

    /**
     * The JSON the sketch's buildTelemetryPayload() produces.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sketchPayload(string $deviceCode, array $overrides = []): array
    {
        return array_merge([
            'device_code' => $deviceCode,
            'temperature' => 4.50,
            'temperature_valid' => true,
            'latitude' => 15.4869000,
            'longitude' => 120.5900000,
            'gps_valid' => true,
            'satellites' => 9,
            'speed_kmph' => 21.40,
            'uptime_ms' => 123456,
        ], $overrides);
    }

    /**
     * @return array{administrator: User, driver: User, device: Device, trip: Trip, product: Product}
     */
    private function activeDelivery(): array
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ColdTraceDeviceSeeder::class);

        $administrator = User::create([
            'role_id' => Role::where('name', 'Administrator')->value('id'),
            'name' => 'ColdTrace Administrator',
            'email' => 'administrator@coldtrace.test',
            'password' => Hash::make('administrator-password'),
            'status' => 'active',
        ]);

        $driver = User::create([
            'role_id' => Role::where('name', 'Driver')->value('id'),
            'name' => 'Ramon Delivery',
            'email' => 'ramon@coldtrace.test',
            'password' => Hash::make('driver-password'),
            'status' => 'active',
        ]);

        $truck = Truck::create([
            'plate_number' => 'CT-1004',
            'model' => 'Isuzu Elf Reefer',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        // The sketch in use publishes as ESP32-CT-1004.
        $device = Device::where('device_code', 'ESP32-CT-1004')->firstOrFail();
        $device->update(['truck_id' => $truck->id]);

        $product = Product::create([
            'name' => 'Fresh Milk',
            'min_temp' => 2.0,
            'max_temp' => 8.0,
            'initial_shelf_life_hours' => 240.0,
            'reference_storage_temp_celsius' => 4.0,
            'activation_energy_j_per_mol' => 83144.0,
        ]);

        $this->actingAs($administrator)->post(route('orders.store'), [
            'driver_id' => $driver->id,
            'expected_delivery_at' => now()->addHours(5)->format('Y-m-d H:i:s'),
            'delivery_address' => 'SM City Tarlac, Tarlac City',
            'delivery_lat' => 15.4869,
            'delivery_lng' => 120.5900,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 120, 'unit' => 'kg'],
            ],
        ]);

        $order = Order::latest('id')->firstOrFail();
        $trip = Trip::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($driver)->patch(
            route('driver.trips.start', $trip),
            ['return_to' => 'dashboard']
        );

        return [
            'administrator' => $administrator,
            'driver' => $driver,
            'device' => $device->fresh(),
            'trip' => $trip->fresh(),
            'product' => $product,
        ];
    }
}
