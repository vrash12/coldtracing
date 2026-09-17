<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ColdChain\TemperatureStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemperatureStatusServiceTest extends TestCase
{
    /**
     * @return array<string, array{float|null, string, string, bool}>
     */
    public static function temperatureCases(): array
    {
        return [
            'no reading' => [null, 'no_data', 'No Data', false],
            'below minimum' => [1.99, 'too_low', 'Too Low', true],
            'at minimum' => [2.0, 'safe', 'Safe', false],
            'inside range' => [5.0, 'safe', 'Safe', false],
            'at maximum' => [8.0, 'safe', 'Safe', false],
            'above maximum' => [8.01, 'too_high', 'Too High', true],
        ];
    }

    #[DataProvider('temperatureCases')]
    public function test_temperature_is_classified_against_product_limits(
        ?float $temperature,
        string $expectedCode,
        string $expectedLabel,
        bool $expectedBreach
    ): void {
        $product = new Product([
            'name' => 'Status Test Product',
            'min_temp' => 2,
            'max_temp' => 8,
            'initial_shelf_life_hours' => 240,
        ]);

        $state = (new TemperatureStatusService)->evaluate(
            $temperature,
            $product
        );

        $this->assertSame($expectedCode, $state['code']);
        $this->assertSame($expectedLabel, $state['label']);
        $this->assertSame($expectedBreach, $state['is_breach']);
    }

    public function test_invalid_product_limits_return_no_data(): void
    {
        $product = new Product([
            'name' => 'Invalid Limits',
            'min_temp' => 8,
            'max_temp' => 2,
            'initial_shelf_life_hours' => 240,
        ]);

        $state = (new TemperatureStatusService)->evaluate(5, $product);

        $this->assertSame('no_data', $state['code']);
        $this->assertSame('No Data', $state['label']);
        $this->assertFalse($state['is_breach']);
    }
}
