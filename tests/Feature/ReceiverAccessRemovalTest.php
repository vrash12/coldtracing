<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReceiverAccessRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_workspace_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('customer.dashboard'));
        $this->assertFalse(Route::has('customer.orders.index'));

        $this->get('/customer/dashboard')->assertNotFound();
        $this->get('/customer/orders')->assertNotFound();
    }

    public function test_receiver_account_cannot_log_in(): void
    {
        $receiverRole = Role::create([
            'name' => 'Receiver',
            'description' => 'Legacy recipient record.',
        ]);

        $receiver = User::create([
            'role_id' => $receiverRole->id,
            'name' => 'Delivery Receiver',
            'email' => 'receiver@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->post(route('login.submit'), [
            'email' => $receiver->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_administrator_user_management_only_lists_admin_and_driver_roles(): void
    {
        $administratorRole = Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'Driver']);
        Role::create(['name' => 'Receiver']);

        $administrator = User::create([
            'role_id' => $administratorRole->id,
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSeeText('Administrator')
            ->assertSeeText('Driver')
            ->assertDontSeeText('Receiver');
    }
}
