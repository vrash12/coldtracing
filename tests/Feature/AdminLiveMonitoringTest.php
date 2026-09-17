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
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLiveMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id'), 'status' => 'active']);
    }

    public function test_admin_sees_the_six_paired_trucks_without_any_orders_or_readings(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->user('Administrator');
        foreach (range(1001, 1006) as $number) {
            $driver = $this->user('Driver');
            $truck = Truck::create(['driver_id' => $driver->id, 'plate_number' => "CT-$number", 'status' => 'available']);
            Device::create(['truck_id' => $truck->id, 'device_code' => "ESP32-CT-$number",
                'mqtt_topic' => "coldtrace/trucks/CT-$number/telemetry", 'status' => 'active']);
        }
        $this->actingAs($admin)->get(route('monitoring.index'))->assertOk()
            ->assertSee('Fleet trucks')->assertSee('ESP32-CT-1006');
        $this->getJson(route('monitoring.latest'))->assertOk()->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.status', 'idle')
            ->assertJsonPath('data.0.trip_id', null)
            ->assertJsonPath('data.0.gps', null)
            ->assertJsonPath('data.5.devices.0.topic', 'coldtrace/trucks/CT-1006/telemetry');
    }

    public function test_admin_keeps_the_latest_valid_gps_when_newer_reading_has_no_position_or_temperature(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->user('Administrator');
        $driver = $this->user('Driver');
        $truck = Truck::create(['driver_id' => $driver->id, 'plate_number' => 'CT-1001', 'status' => 'available']);
        $device = Device::create(['truck_id' => $truck->id, 'device_code' => 'ESP32-CT-1001',
            'mqtt_topic' => 'coldtrace/trucks/CT-1001/telemetry', 'status' => 'active']);
        $product = Product::create(['name' => 'Test cargo', 'min_temp' => 2, 'max_temp' => 8, 'initial_shelf_life_hours' => 100]);
        $order = Order::create(['order_code' => 'MONITOR-001', 'created_by' => $admin->id, 'receiver_id' => $admin->id,
            'driver_id' => $driver->id, 'product_id' => $product->id, 'quantity' => 10, 'unit' => 'kg',
            'pickup_address' => 'Pickup', 'pickup_lat' => 14.6, 'pickup_lng' => 121.1,
            'delivery_address' => 'Delivery', 'delivery_lat' => 14.7, 'delivery_lng' => 121.2, 'status' => 'in_transit']);
        $trip = Trip::create(['order_id' => $order->id, 'truck_id' => $truck->id, 'driver_id' => $driver->id, 'receiver_id' => $admin->id,
            'product_id' => $product->id, 'status' => 'in_progress', 'origin_address' => 'Pickup',
            'origin_lat' => 14.6, 'origin_lng' => 121.1, 'destination_address' => 'Delivery',
            'destination_lat' => 14.7, 'destination_lng' => 121.2]);
        TelemetryLog::create(['trip_id' => $trip->id, 'device_id' => $device->id, 'latitude' => 0,
            'longitude' => 121.05, 'temperature' => 4, 'recorded_at' => now()->subSeconds(10)]);
        TelemetryLog::create(['trip_id' => $trip->id, 'device_id' => $device->id, 'latitude' => null,
            'longitude' => null, 'temperature' => null, 'recorded_at' => now()]);

        $this->actingAs($admin)->getJson(route('monitoring.latest'))->assertOk()
            ->assertJsonPath('data.0.gps.lat', 0)
            ->assertJsonPath('data.0.gps.lng', 121.05)
            ->assertJsonPath('data.0.latestTelemetry.temperature', null);

        $trip->update(['status' => 'completed']);
        $this->getJson(route('monitoring.latest'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'idle')->assertJsonPath('data.0.gps.lng', 121.05);
    }

    public function test_fleet_location_feed_is_only_available_to_administrators(): void
    {
        $this->seed(RoleSeeder::class);
        $this->getJson(route('monitoring.latest'))->assertUnauthorized();
        $this->actingAs($this->user('Driver'))->getJson(route('monitoring.latest'))->assertForbidden();
    }
}
