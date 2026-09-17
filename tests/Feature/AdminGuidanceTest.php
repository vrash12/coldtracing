<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Product;
use App\Models\Role;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\ColdTraceDeviceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A new administrator cannot dispatch an order until a product exists and a
 * driver holds a truck with a paired device. Nothing in the data model says so,
 * so these cases pin the on-screen guidance that does.
 */
class AdminGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_shows_the_setup_checklist(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs($this->administrator())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Finish setting up ColdTrace')
            ->assertSeeText('Product catalogue available')
            ->assertSeeText('Create a driver account')
            ->assertSeeText('Register a truck and assign its driver')
            ->assertSeeText('Pair a tracking device with that truck');
    }

    public function test_the_checklist_disappears_once_setup_is_complete(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ColdTraceDeviceSeeder::class);

        $administrator = $this->administrator();
        $driver = $this->driver();

        Product::create([
            'name' => 'Fresh Milk',
            'min_temp' => 2.0,
            'max_temp' => 8.0,
            'initial_shelf_life_hours' => 240.0,
            'reference_storage_temp_celsius' => 4.0,
            'activation_energy_j_per_mol' => 83144.0,
        ]);

        $truck = Truck::create([
            'plate_number' => 'ABC-1234',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        Device::where('device_code', 'ESP32-CT-1001')->firstOrFail()
            ->update(['truck_id' => $truck->id]);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Finish setting up ColdTrace');
    }

    public function test_the_order_form_explains_an_empty_product_catalogue(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs($this->administrator())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSeeText('There are no products to choose from yet.')
            ->assertSeeText('Contact the system maintainer to configure the product catalogue.')
            ->assertDontSee('href="'.url('/products'), false);
    }

    public function test_the_order_form_explains_that_no_driver_can_be_assigned_yet(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs($this->administrator())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSeeText('No driver is available to assign yet.');
    }

    public function test_the_order_form_drops_its_guidance_once_the_catalogue_and_fleet_are_ready(): void
    {
        $this->seed(RoleSeeder::class);

        $driver = $this->driver();

        Product::create([
            'name' => 'Fresh Milk',
            'min_temp' => 2.0,
            'max_temp' => 8.0,
            'initial_shelf_life_hours' => 240.0,
            'reference_storage_temp_celsius' => 4.0,
            'activation_energy_j_per_mol' => 83144.0,
        ]);

        Truck::create([
            'plate_number' => 'ABC-1234',
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => 'available',
        ]);

        $this->actingAs($this->administrator())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertDontSeeText('There are no products to choose from yet.')
            ->assertDontSeeText('No driver is available to assign yet.');
    }

    public function test_the_customer_field_is_hidden_while_no_receiver_records_exist(): void
    {
        $this->seed(RoleSeeder::class);

        // User management can only create administrators and drivers, so on a
        // fresh install this control could never be filled in.
        $this->actingAs($this->administrator())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertDontSee('name="receiver_id"', false);
    }

    public function test_the_customer_field_appears_when_a_receiver_record_exists(): void
    {
        $this->seed(RoleSeeder::class);

        User::create([
            'role_id' => Role::where('name', 'Receiver')->value('id'),
            'name' => 'Corner Store',
            'email' => 'store@coldtrace.test',
            'password' => Hash::make('receiver-password'),
            'status' => 'active',
        ]);

        $this->actingAs($this->administrator())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee('name="receiver_id"', false)
            ->assertSeeText('Corner Store');
    }

    public function test_a_rejected_order_lists_every_problem_at_the_top_of_the_form(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->from(route('orders.create'))
            ->post(route('orders.store'), [
                'delivery_address' => '',
                'items' => [],
            ])
            ->assertRedirect(route('orders.create'));

        $this->actingAs($administrator)
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSeeText('This form could not be saved')
            ->assertSeeText('Add at least one item to the order.');
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
}
