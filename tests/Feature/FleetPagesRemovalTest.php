<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FleetPagesRemovalTest extends TestCase
{
    public function test_fleet_management_routes_are_not_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertFalse(str_starts_with($route->uri(), 'fleet'));
        }

        foreach (['/fleet', '/fleet/trucks/create', '/fleet/trucks/1/edit', '/fleet/devices/1/edit'] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->post('/fleet/devices/sync')->assertNotFound();
        $this->post('/fleet/trucks', [])->assertNotFound();
        $this->put('/fleet/trucks/1', [])->assertNotFound();
        $this->put('/fleet/devices/1', [])->assertNotFound();
        $this->delete('/fleet/trucks/1')->assertNotFound();
    }
}
