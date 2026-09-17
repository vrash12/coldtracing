<?php

namespace Tests\Feature;

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
 * Every administrator and driver page must actually render. A controller can pass
 * its tests while the view it returns is missing or calls a method that does not
 * exist, so these cases request the pages themselves and check for real content.
 */
class WorkspacePagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_account_form_renders_for_creating_and_editing(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = $this->administrator();
        $driver = $this->driver();

        $this->actingAs($administrator)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="role_id"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSeeText('Administrator')
            ->assertSeeText('Driver')
            // The receiver role must never be offered as a login account.
            ->assertDontSeeText('Receiver');

        $this->actingAs($administrator)
            ->get(route('users.edit', $driver))
            ->assertOk()
            ->assertSee('value="'.$driver->email.'"', false)
            ->assertSeeText('Leave blank to keep current');
    }

    public function test_the_driver_trip_list_and_detail_pages_render(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ColdTraceDeviceSeeder::class);

        $administrator = $this->administrator();
        $driver = $this->driver();
        $product = $this->product();

        Truck::create([
            'plate_number' => 'TRIP-0001',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        $this->actingAs($administrator)->post(route('orders.store'), [
            'driver_id' => $driver->id,
            'expected_delivery_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
            'delivery_address' => 'SM City Tarlac, Tarlac City',
            'delivery_lat' => 15.4869,
            'delivery_lng' => 120.5900,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 40, 'unit' => 'kg'],
            ],
        ]);

        $order = Order::latest('id')->firstOrFail();
        $trip = Trip::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($driver)
            ->get(route('driver.trips.index'))
            ->assertOk()
            ->assertSeeText($order->order_code)
            ->assertSeeText('SM City Tarlac, Tarlac City')
            ->assertSeeText($product->name);

        $this->actingAs($driver)
            ->get(route('driver.trips.show', $trip))
            ->assertOk()
            ->assertSeeText($order->order_code)
            ->assertSeeText('Start trip')
            ->assertSeeText('No alerts on this trip');

        $this->actingAs($driver)
            ->get(route('driver.trips.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSeeText('No trips to show');
    }

    public function test_a_driver_cannot_open_another_drivers_trip(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = $this->administrator();
        $owner = $this->driver();
        $product = $this->product();

        $intruder = User::create([
            'role_id' => Role::where('name', 'Driver')->value('id'),
            'name' => 'Other Driver',
            'email' => 'other@coldtrace.test',
            'password' => Hash::make('driver-password'),
            'status' => 'active',
        ]);

        Truck::create([
            'plate_number' => 'OWNER-0001',
            'driver_id' => $owner->id,
            'driver_name' => $owner->name,
            'status' => 'available',
        ]);

        $this->actingAs($administrator)->post(route('orders.store'), [
            'driver_id' => $owner->id,
            'expected_delivery_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
            'delivery_address' => 'SM City Tarlac, Tarlac City',
            'delivery_lat' => 15.4869,
            'delivery_lng' => 120.5900,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 12, 'unit' => 'kg'],
            ],
        ]);

        $trip = Trip::latest('id')->firstOrFail();

        // "Not found" rather than "forbidden", so one driver cannot discover
        // which trips belong to another.
        $this->actingAs($intruder)
            ->get(route('driver.trips.show', $trip))
            ->assertNotFound();

        $this->actingAs($administrator)
            ->get(route('driver.trips.index'))
            ->assertForbidden();
    }

    public function test_starting_a_trip_from_its_detail_page_returns_to_that_page(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = $this->administrator();
        $driver = $this->driver();
        $product = $this->product();

        Truck::create([
            'plate_number' => 'RETURN-0001',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        $this->actingAs($administrator)->post(route('orders.store'), [
            'driver_id' => $driver->id,
            'expected_delivery_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
            'delivery_address' => 'SM City Tarlac, Tarlac City',
            'delivery_lat' => 15.4869,
            'delivery_lng' => 120.5900,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 8, 'unit' => 'kg'],
            ],
        ]);

        $trip = Trip::latest('id')->firstOrFail();

        // Without return_to the controller redirects to the trip detail page,
        // which must exist and render.
        $this->actingAs($driver)
            ->patch(route('driver.trips.start', $trip))
            ->assertRedirect(route('driver.trips.show', $trip));

        $this->actingAs($driver)
            ->get(route('driver.trips.show', $trip))
            ->assertOk()
            ->assertSeeText('Complete trip');
    }

    private function administrator(): User
    {
        return User::create([
            'role_id' => Role::where('name', 'Administrator')->value('id'),
            'name' => 'ColdTrace Administrator',
            'email' => 'administrator@coldtrace.test',
            'password' => Hash::make('administrator-password'),
            'status' => 'active',
        ]);
    }

    private function driver(): User
    {
        return User::create([
            'role_id' => Role::where('name', 'Driver')->value('id'),
            'name' => 'Ramon Delivery',
            'email' => 'ramon@coldtrace.test',
            'password' => Hash::make('driver-password'),
            'status' => 'active',
        ]);
    }

    private function product(): Product
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
