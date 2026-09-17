<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ColdChain\MktCalculatorService;
use PHPUnit\Framework\TestCase;

class MktCalculatorServiceTest extends TestCase
{
    public function test_telemetry_mkt_uses_timestamp_weights_for_irregular_readings(): void
    {
        $calculator = new MktCalculatorService;
        $activationEnergy = 50000.0;

        $actual = $calculator->calculateFromTelemetry(
            telemetryLogs: [
                [
                    'temperature' => 2.0,
                    'recorded_at' => '2026-08-23T00:00:00+00:00',
                ],
                [
                    'temperature' => 6.0,
                    'recorded_at' => '2026-08-23T00:10:00+00:00',
                ],
                [
                    'temperature' => 12.0,
                    'recorded_at' => '2026-08-23T00:40:00+00:00',
                ],
            ],
            activationEnergyJPerMol: $activationEnergy,
            precision: 4
        );

        $expected = $calculator->calculateWeighted(
            readings: [
                [
                    'temperature' => 2.0,
                    'duration_seconds' => 300,
                ],
                [
                    'temperature' => 6.0,
                    'duration_seconds' => 1200,
                ],
                [
                    'temperature' => 12.0,
                    'duration_seconds' => 900,
                ],
            ],
            activationEnergyJPerMol: $activationEnergy,
            precision: 4
        );

        $equallySpaced = $calculator->calculate(
            temperaturesCelsius: [2.0, 6.0, 12.0],
            activationEnergyJPerMol: $activationEnergy,
            precision: 4
        );

        $this->assertSame($expected, $actual);
        $this->assertNotSame($equallySpaced, $actual);
    }

    public function test_telemetry_mkt_uses_the_supplied_activation_energy(): void
    {
        $calculator = new MktCalculatorService;
        $logs = [
            [
                'temperature' => 2.0,
                'recorded_at' => '2026-08-23T00:00:00+00:00',
            ],
            [
                'temperature' => 12.0,
                'recorded_at' => '2026-08-23T01:00:00+00:00',
            ],
        ];

        $productSpecificMkt = $calculator->calculateFromTelemetry(
            telemetryLogs: $logs,
            activationEnergyJPerMol: 50000.0,
            precision: 4
        );

        $defaultMkt = $calculator->calculateFromTelemetry(
            telemetryLogs: $logs,
            precision: 4
        );

        $this->assertNotSame($defaultMkt, $productSpecificMkt);
    }
}
