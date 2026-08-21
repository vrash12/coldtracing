<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Administrator' => 'Manages ColdTrace users, orders, reports, and fleet operations.',
            'Driver' => 'Manages assigned deliveries, routes, trips, and telemetry monitoring.',
            'Receiver' => 'Creates and monitors cold-chain delivery orders.',
        ];

        foreach ($roles as $name => $description) {
            Role::updateOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }
    }
}
