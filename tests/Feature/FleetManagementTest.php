<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_middleware_blocks_cross_role_workspace_access(): void
    {
        $roles = $this->createRoles();
        $driver = $this->createUser($roles['Driver']);
        $receiver = $this->createUser($roles['Receiver']);

        $this->actingAs($driver)
            ->get('/customer/dashboard')
            ->assertNotFound();

        $this->actingAs($receiver)
            ->get(route('driver.dashboard'))
            ->assertForbidden();

        $this->actingAs($receiver)
            ->get('/reports')
            ->assertNotFound();
    }

    public function test_inactive_truck_is_not_available_for_new_order_assignments(): void
    {
        $context = $this->deliveryContext('completed');
        $context['truck']->update(['status' => 'inactive']);
        $this->actingAs($context['administrator'])->postJson(route('orders.store'), [
            'receiver_id' => $context['receiver']->id,
            'driver_id' => $context['driver']->id,
            'delivery_address' => 'Test delivery destination',
            'delivery_lat' => 14.7,
            'delivery_lng' => 121.05,
            'expected_delivery_at' => now()->addDay()->toDateTimeString(),
            'items' => [['product_id' => $context['product']->id, 'quantity' => 10, 'unit' => 'kg']],
        ])->assertUnprocessable()->assertJsonValidationErrors('driver_id');
        $this->assertDatabaseCount('orders', 1);
    }

    private function deliveryContext(string $status): array
    {
        $roles = $this->createRoles();
        $administrator = $this->createUser($roles['Administrator']);
        $driver = $this->createUser($roles['Driver']);
        $receiver = $this->createUser($roles['Receiver']);
        $truck = Truck::create([
            'driver_id' => $driver->id,
            'plate_number' => 'CT-GUARD-1',
            'status' => $status === 'in_progress' ? 'in_trip' : 'available',
        ]);
        $device = Device::create([
            'truck_id' => $truck->id,
            'device_code' => 'ESP32-CT-1001',
            'mqtt_topic' => 'coldtrace/trucks/CT-1001/telemetry',
            'status' => 'active',
        ]);
        $product = Product::create([
            'name' => 'Fleet test product',
            'min_temp' => 2,
            'max_temp' => 8,
            'initial_shelf_life_hours' => 240,
        ]);
        $order = Order::create([
            'order_code' => 'ORD-FLEET-1',
            'created_by' => $receiver->id,
            'receiver_id' => $receiver->id,
            'driver_id' => $driver->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit' => 'kg',
            'pickup_address' => 'Test pickup',
            'delivery_address' => 'Test destination',
            'status' => 'assigned',
        ]);
        $trip = Trip::create([
            'order_id' => $order->id,
            'truck_id' => $truck->id,
            'product_id' => $product->id,
            'driver_id' => $driver->id,
            'receiver_id' => $receiver->id,
            'origin_address' => 'Test pickup',
            'destination_address' => 'Test destination',
            'status' => $status,
        ]);

        return compact('roles', 'administrator', 'driver', 'receiver', 'truck', 'device', 'product', 'order', 'trip');
    }

    /**
     * @return array<string, Role>
     */
    private function createRoles(): array
    {
        return collect(['Administrator', 'Driver', 'Receiver'])
            ->mapWithKeys(function (string $name) {
                $role = Role::create([
                    'name' => $name,
                    'description' => "{$name} test role.",
                ]);

                return [$name => $role];
            })
            ->all();
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
            ...$attributes,
        ]);
    }
}
