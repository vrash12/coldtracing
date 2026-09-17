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
use Database\Seeders\ColdTraceDeviceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Walks the complete ColdTrace delivery journey through the same HTTP routes a
 * real operator uses, in the order documented in docs/USER_MANUAL.md. Each step
 * asserts the state the manual tells the reader to expect, so the manual and the
 * application cannot drift apart silently.
 */
class DeliveryJourneyWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_TOKEN = 'walkthrough-device-token';

    public function test_administrator_and_driver_complete_a_full_delivery(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = $this->createAdministrator();

        // Step 1 — the administrator signs in and lands on the operations dashboard.
        $this->post(route('login.submit'), [
            'email' => $administrator->email,
            'password' => 'administrator-password',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk();

        // Step 2 — the administrator creates a driver account.
        $driverRoleId = Role::where('name', 'Driver')->value('id');

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                'role_id' => $driverRoleId,
                'name' => 'Ramon Delivery',
                'email' => 'ramon@coldtrace.test',
                'phone' => '09171234567',
                'status' => 'active',
                'password' => 'driver-password',
                'password_confirmation' => 'driver-password',
            ])
            ->assertRedirect(route('users.index'));

        $driver = User::where('email', 'ramon@coldtrace.test')->firstOrFail();
        $this->assertTrue($driver->isDriver());

        // Fleet assignments are configured outside the administrator workspace.
        $truck = Truck::create([
            'plate_number' => 'ABC-1234',
            'model' => 'Isuzu Elf Reefer',
            'driver_id' => $driver->id,
            'status' => 'available',
        ]);
        $this->assertSame($driver->id, $truck->driver_id);

        // Provision a device and its existing truck pairing for this delivery.
        $this->seed(ColdTraceDeviceSeeder::class);

        $device = Device::where('device_code', 'ESP32-CT-1001')->firstOrFail();

        $device->update(['truck_id' => $truck->id]);

        $this->assertSame($truck->id, $device->fresh()->truck_id);

        // Products have no management screen, so the catalogue is seeded directly.
        $product = $this->createProduct();

        // Step 5 — the administrator creates an order and assigns it to the driver.
        // Assigning a driver immediately creates the matching pending trip.
        $this->actingAs($administrator)
            ->post(route('orders.store'), [
                'receiver_id' => null,
                'driver_id' => $driver->id,
                'expected_delivery_at' => now()->addHours(6)->format('Y-m-d H:i:s'),
                'delivery_address' => 'SM City Tarlac, Tarlac City',
                'delivery_lat' => 15.4869,
                'delivery_lng' => 120.5900,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 120,
                        'unit' => 'kg',
                    ],
                ],
                'notes' => 'Keep the reefer door closed between stops.',
            ])
            ->assertRedirect(route('orders.index'));

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('assigned', $order->status);
        $this->assertSame($driver->id, $order->driver_id);

        $trip = Trip::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $trip->status);
        $this->assertSame($truck->id, $trip->truck_id);

        // The driver is notified of the new assignment.
        $this->assertSame(1, $driver->fresh()->unreadNotifications()->count());

        // Step 6 — the driver signs in and is sent to the driver dashboard.
        $this->post(route('login.submit'), [
            'email' => $driver->email,
            'password' => 'driver-password',
        ])->assertRedirect(route('driver.dashboard'));

        $this->actingAs($driver)
            ->get(route('driver.dashboard'))
            ->assertOk()
            ->assertSeeText($order->order_code);

        $this->actingAs($driver)
            ->get(route('driver.orders.index'))
            ->assertOk();

        $this->actingAs($driver)
            ->get(route('driver.orders.show', $order))
            ->assertOk();

        // Step 7 — the driver starts the trip from the dashboard.
        $this->actingAs($driver)
            ->patch(route('driver.trips.start', $trip), ['return_to' => 'dashboard'])
            ->assertRedirect(route('driver.dashboard'));

        $this->assertSame('in_progress', $trip->fresh()->status);
        $this->assertSame('in_transit', $order->fresh()->status);
        $this->assertSame('in_trip', $truck->fresh()->status);

        // Step 8 — the tracking device reports a reading inside the safe range.
        config()->set('coldtrace.telemetry.token', self::DEVICE_TOKEN);

        $this->withHeader('X-ColdTrace-Token', self::DEVICE_TOKEN)
            ->postJson('/api/telemetry', [
                'device_code' => $device->device_code,
                'temperature' => 3.0,
                'latitude' => 15.0000,
                'longitude' => 120.7000,
                'gps_valid' => true,
                'satellites' => 9,
            ])
            ->assertCreated()
            ->assertJsonPath('data.temperature_status', 'Safe')
            ->assertJsonPath('data.trip_id', $trip->id);

        $this->assertSame(0, Alert::where('trip_id', $trip->id)->count());
        $this->assertSame('active', $device->fresh()->status);

        // Step 9 — a reading above the product maximum raises a critical alert.
        $this->travel(30)->minutes();

        $this->withHeader('X-ColdTrace-Token', self::DEVICE_TOKEN)
            ->postJson('/api/telemetry', [
                'device_code' => $device->device_code,
                'temperature' => 12.5,
                'latitude' => 15.2000,
                'longitude' => 120.6500,
                'gps_valid' => true,
                'satellites' => 9,
            ])
            ->assertCreated()
            ->assertJsonPath('data.temperature_status', 'Too High');

        $alert = Alert::where('trip_id', $trip->id)->firstOrFail();
        $this->assertSame('temperature_too_high', $alert->type);
        $this->assertSame('critical', $alert->severity);
        $this->assertFalse((bool) $alert->is_resolved);

        // MKT and remaining shelf life are recalculated from the whole trip.
        $latestTelemetry = $trip->fresh()->latestTelemetry()->firstOrFail();
        $this->assertNotNull($latestTelemetry->mkt_value);
        $this->assertNotNull($latestTelemetry->rsl_hours);
        $this->assertLessThan(
            (float) $product->initial_shelf_life_hours,
            (float) $latestTelemetry->rsl_hours
        );

        // The alert is visible to the administrator on live fleet monitoring.
        $this->actingAs($administrator)
            ->get(route('monitoring.index'))
            ->assertOk();

        // Step 10 — the temperature recovers and ColdTrace resolves the alert.
        $this->travel(30)->minutes();

        $this->withHeader('X-ColdTrace-Token', self::DEVICE_TOKEN)
            ->postJson('/api/telemetry', [
                'device_code' => $device->device_code,
                'temperature' => 3.5,
                'latitude' => 15.4000,
                'longitude' => 120.6000,
                'gps_valid' => true,
                'satellites' => 9,
            ])
            ->assertCreated()
            ->assertJsonPath('data.temperature_status', 'Safe');

        $this->assertTrue((bool) $alert->fresh()->is_resolved);
        $this->assertNotNull($alert->fresh()->resolved_at);

        // Step 11 — the driver completes the trip.
        $this->actingAs($driver)
            ->patch(route('driver.trips.complete', $trip), ['return_to' => 'dashboard'])
            ->assertRedirect(route('driver.dashboard'));

        $this->assertSame('completed', $trip->fresh()->status);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('available', $truck->fresh()->status);

        // Step 12 — the administrator reviews the finished order.
        $this->actingAs($administrator)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSeeText($order->order_code);

        $this->actingAs($administrator)
            ->get(route('orders.index'))
            ->assertOk();
    }

    public function test_an_order_cannot_be_assigned_to_a_driver_without_a_truck(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = $this->createAdministrator();
        $product = $this->createProduct();

        $driver = User::create([
            'role_id' => Role::where('name', 'Driver')->value('id'),
            'name' => 'Driver Without Truck',
            'email' => 'no-truck@coldtrace.test',
            'password' => Hash::make('driver-password'),
            'status' => 'active',
        ]);

        $this->actingAs($administrator)
            ->post(route('orders.store'), [
                'driver_id' => $driver->id,
                'expected_delivery_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
                'delivery_address' => 'SM City Tarlac, Tarlac City',
                'delivery_lat' => 15.4869,
                'delivery_lng' => 120.5900,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10, 'unit' => 'kg'],
                ],
            ])
            ->assertSessionHasErrors('driver_id');

        $this->assertSame(0, Order::count());
    }

    public function test_a_driver_cannot_be_double_booked_for_the_same_delivery_slot(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = $this->createAdministrator();
        $product = $this->createProduct();

        $driver = User::create([
            'role_id' => Role::where('name', 'Driver')->value('id'),
            'name' => 'Busy Driver',
            'email' => 'busy@coldtrace.test',
            'password' => Hash::make('driver-password'),
            'status' => 'active',
        ]);

        Truck::create([
            'plate_number' => 'XYZ-9999',
            'model' => 'Fuso Canter Reefer',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        $slot = now()->addHours(5)->format('Y-m-d H:i:00');

        $payload = [
            'driver_id' => $driver->id,
            'expected_delivery_at' => $slot,
            'delivery_address' => 'SM City Tarlac, Tarlac City',
            'delivery_lat' => 15.4869,
            'delivery_lng' => 120.5900,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit' => 'kg'],
            ],
        ];

        $this->actingAs($administrator)
            ->post(route('orders.store'), $payload)
            ->assertRedirect(route('orders.index'));

        $this->actingAs($administrator)
            ->post(route('orders.store'), $payload)
            ->assertSessionHasErrors(['driver_id', 'expected_delivery_at']);

        $this->assertSame(1, Order::count());
    }

    public function test_an_in_transit_order_is_locked_against_administrative_editing(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ColdTraceDeviceSeeder::class);

        $administrator = $this->createAdministrator();
        $product = $this->createProduct();

        $driver = User::create([
            'role_id' => Role::where('name', 'Driver')->value('id'),
            'name' => 'Active Driver',
            'email' => 'active@coldtrace.test',
            'password' => Hash::make('driver-password'),
            'status' => 'active',
        ]);

        $truck = Truck::create([
            'plate_number' => 'LOCK-0001',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        $this->actingAs($administrator)->post(route('orders.store'), [
            'driver_id' => $driver->id,
            'expected_delivery_at' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'delivery_address' => 'SM City Tarlac, Tarlac City',
            'delivery_lat' => 15.4869,
            'delivery_lng' => 120.5900,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit' => 'kg'],
            ],
        ]);

        $order = Order::latest('id')->firstOrFail();
        $trip = Trip::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($driver)
            ->patch(route('driver.trips.start', $trip), ['return_to' => 'dashboard']);

        $this->assertSame('in_transit', $order->fresh()->status);

        $this->actingAs($administrator)
            ->put(route('orders.update', $order), [
                'driver_id' => $driver->id,
                'expected_delivery_at' => now()->addHours(9)->format('Y-m-d H:i:s'),
                'delivery_address' => 'Changed Address, Tarlac City',
                'delivery_lat' => 15.5000,
                'delivery_lng' => 120.6000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 99, 'unit' => 'kg'],
                ],
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame(
            'SM City Tarlac, Tarlac City',
            $order->fresh()->delivery_address
        );

        // The truck stays in service while its trip is running.
        $this->assertSame('in_trip', $truck->fresh()->status);
    }

    public function test_telemetry_is_rejected_when_no_trip_is_active_for_the_truck(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ColdTraceDeviceSeeder::class);

        $truck = Truck::create([
            'plate_number' => 'IDLE-0001',
            'status' => 'available',
        ]);

        $device = Device::where('device_code', 'ESP32-CT-1002')->firstOrFail();
        $device->update(['truck_id' => $truck->id]);

        config()->set('coldtrace.telemetry.token', self::DEVICE_TOKEN);

        $this->withHeader('X-ColdTrace-Token', self::DEVICE_TOKEN)
            ->postJson('/api/telemetry', [
                'device_code' => $device->device_code,
                'temperature' => 4.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No active trip is connected to this device.');
    }

    private function createAdministrator(): User
    {
        return User::create([
            'role_id' => Role::where('name', 'Administrator')->value('id'),
            'name' => 'ColdTrace Administrator',
            'email' => 'administrator@coldtrace.test',
            'password' => Hash::make('administrator-password'),
            'status' => 'active',
        ]);
    }

    private function createProduct(): Product
    {
        return Product::create([
            'name' => 'Fresh Milk',
            'min_temp' => 2.0,
            'max_temp' => 8.0,
            'initial_shelf_life_hours' => 240.0,
            'reference_storage_temp_celsius' => 4.0,
            'activation_energy_j_per_mol' => 83144.0,
        ]);
    }
}
