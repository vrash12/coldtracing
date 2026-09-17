<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Role;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\ColdTraceDeviceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The driver's route planner subscribes to MQTT in the browser. It must be
 * scoped to that driver's own truck from the first page load, because the
 * fallback topic is a wildcard carrying the whole fleet.
 */
class DriverLiveTelemetryScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ColdTraceDeviceSeeder::class);

        config()->set('services.hivemq.websocket_url', 'wss://broker.example.test:8884/mqtt');
        config()->set('services.hivemq.username', 'bridge-user');
        config()->set('services.hivemq.password', 'bridge-password');
    }

    public function test_the_page_subscribes_to_the_drivers_own_truck_before_any_reading_arrives(): void
    {
        $driver = $this->driver('ramon@coldtrace.test');

        $truck = Truck::create([
            'plate_number' => 'CT-1004',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        Device::where('device_code', 'ESP32-CT-1004')->firstOrFail()
            ->update(['truck_id' => $truck->id]);

        $this->actingAs($driver)
            ->get(route('driver.orders.index'))
            ->assertOk()
            ->assertSee('coldtrace\/trucks\/CT-1004\/telemetry', false)
            ->assertSee('ESP32-CT-1004', false)
            // The fleet-wide wildcard must not be what this driver listens on.
            ->assertDontSee('coldtrace\/trucks\/+\/telemetry', false);
    }

    public function test_a_driver_is_not_given_another_trucks_device_code(): void
    {
        $owner = $this->driver('owner@coldtrace.test');
        $other = $this->driver('other@coldtrace.test', 'Other Driver');

        $ownerTruck = Truck::create([
            'plate_number' => 'CT-1004',
            'driver_id' => $owner->id,
            'driver_name' => $owner->name,
            'status' => 'available',
        ]);

        $otherTruck = Truck::create([
            'plate_number' => 'CT-1005',
            'driver_id' => $other->id,
            'driver_name' => $other->name,
            'status' => 'available',
        ]);

        Device::where('device_code', 'ESP32-CT-1004')->firstOrFail()
            ->update(['truck_id' => $ownerTruck->id]);
        Device::where('device_code', 'ESP32-CT-1005')->firstOrFail()
            ->update(['truck_id' => $otherTruck->id]);

        $this->actingAs($owner)
            ->get(route('driver.orders.index'))
            ->assertOk()
            ->assertSee('ESP32-CT-1004', false)
            ->assertDontSee('ESP32-CT-1005', false);
    }

    public function test_a_driver_with_no_paired_device_gets_no_device_filter(): void
    {
        $driver = $this->driver('nodevice@coldtrace.test');

        Truck::create([
            'plate_number' => 'CT-9999',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        // The browser script refuses every payload when the code is null, so
        // the wildcard topic cannot leak another truck onto this driver's map.
        $this->actingAs($driver)
            ->get(route('driver.orders.index'))
            ->assertOk()
            ->assertSee('const expectedDeviceCode = null;', false);
    }

    private function driver(string $email, string $name = 'Ramon Delivery'): User
    {
        return User::create([
            'role_id' => Role::where('name', 'Driver')->value('id'),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('driver-password'),
            'status' => 'active',
        ]);
    }
}
