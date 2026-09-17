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
            // Retained only for compatibility with existing recipient-linked records.
            // Receiver accounts cannot authenticate or access a workspace.
            'Receiver' => 'Legacy recipient record (no system login access).',
        ];

        foreach ($roles as $name => $description) {
            Role::updateOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }
    }
}
