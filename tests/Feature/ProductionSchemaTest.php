<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialAdministratorSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_production_schema_contains_the_application_tables_and_columns(): void
    {
        foreach ([
            'roles',
            'users',
            'trucks',
            'devices',
            'products',
            'orders',
            'order_items',
            'trips',
            'telemetry_logs',
            'alerts',
            'notifications',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('users', [
            'role_id',
            'phone',
            'permanent_delivery_address',
            'permanent_delivery_lat',
            'permanent_delivery_lng',
            'status',
        ]));

        $this->assertTrue(Schema::hasColumns('products', [
            'min_temp',
            'max_temp',
            'initial_shelf_life_hours',
            'reference_storage_temp_celsius',
            'activation_energy_j_per_mol',
        ]));

        $this->assertTrue(Schema::hasColumns('orders', [
            'order_code',
            'created_by',
            'receiver_id',
            'driver_id',
            'delivery_address',
            'status',
        ]));

        $this->assertTrue(Schema::hasColumns('trips', [
            'order_id',
            'truck_id',
            'product_id',
            'driver_id',
            'receiver_id',
            'status',
        ]));

        $this->assertTrue(Schema::hasColumns('telemetry_logs', [
            'trip_id',
            'device_id',
            'temperature',
            'mkt_value',
            'rsl_hours',
            'recorded_at',
        ]));
    }

    public function test_the_initial_administrator_is_created_only_from_explicit_configuration(): void
    {
        config()->set('coldtrace.initial_admin', [
            'name' => 'ColdTrace Administrator',
            'email' => 'admin@example.test',
            'password' => 'deployment-test-password',
        ]);

        $this->seed(RoleSeeder::class);
        $this->seed(InitialAdministratorSeeder::class);

        $administrator = User::with('role')
            ->where('email', 'admin@example.test')
            ->firstOrFail();

        $this->assertSame('ColdTrace Administrator', $administrator->name);
        $this->assertSame('Administrator', $administrator->role->name);
        $this->assertSame('active', $administrator->status);
        $this->assertTrue(Hash::check(
            'deployment-test-password',
            $administrator->password
        ));
    }
}
