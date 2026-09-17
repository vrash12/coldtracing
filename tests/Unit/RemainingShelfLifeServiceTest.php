<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ColdChain\RemainingShelfLifeService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RemainingShelfLifeServiceTest extends TestCase
{
    public function test_product_estimate_uses_explicit_scientific_inputs(): void
    {
        $product = new Product([
            'name' => 'Validated Vaccine',
            'min_temp' => 2,
            'max_temp' => 8,
            'initial_shelf_life_hours' => 240,
            'reference_storage_temp_celsius' => 5,
            'activation_energy_j_per_mol' => 50000,
        ]);

        $result = (new RemainingShelfLifeService)->calculateForProduct(
            product: $product,
            elapsedHours: 24,
            mktCelsius: 8
        );

        $this->assertSame(
            'product.reference_storage_temp_celsius',
            $result['reference_temperature_source']
        );
        $this->assertSame(
            'product.activation_energy_j_per_mol',
            $result['activation_energy_source']
        );
        $this->assertSame(50000.0, $result['activation_energy_j_per_mol']);
        $this->assertLessThan(240.0, $result['remaining_hours']);
    }

    public function test_product_estimate_rejects_missing_scientific_inputs(): void
    {
        $product = new Product([
            'name' => 'Unvalidated Product',
            'min_temp' => 2,
            'max_temp' => 8,
            'initial_shelf_life_hours' => 240,
        ]);

        $issues = (new RemainingShelfLifeService)
            ->productProfileIssues($product);

        $this->assertSame([
            'reference_storage_temp_celsius must be above absolute zero',
            'activation_energy_j_per_mol must be greater than zero',
        ], $issues);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'validated reference_storage_temp_celsius'
        );

        (new RemainingShelfLifeService)->calculateForProduct(
            product: $product,
            elapsedHours: 24,
            mktCelsius: 8
        );
    }
}
