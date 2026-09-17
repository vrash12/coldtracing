<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductPagesRemovalTest extends TestCase
{
    public function test_product_management_routes_are_not_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertFalse(str_starts_with($route->uri(), 'products'));
        }

        $this->get('/products')->assertNotFound();
        $this->get('/products/create')->assertNotFound();
        $this->post('/products', [])->assertNotFound();
    }
}
