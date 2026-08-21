<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use InvalidArgumentException;

final class MktCalculatorService
{
    /**
     * Universal gas constant in joules per mole-kelvin.
     */
    public const GAS_CONSTANT_J_PER_MOL_K = 8.314462618;

    /**
     * Commonly used default activation energy:
     * 83.144 kJ/mol = 83,144 J/mol.
     *
     * This should be replaced with a product-specific value
     * when validated scientific data is available.
     */
    public const DEFAULT_ACTIVATION_ENERGY_J_PER_MOL = 83144.0;

    /**
     * Calculate Mean Kinetic Temperature using equally spaced readings.
     *
     * @param array<int, float|int|string|null> $temperaturesCelsius
     */
    public function calculate(
        array $temperaturesCelsius,
        ?float $activationEnergyJPerMol = null,
        int $precision = 2
    ): ?float {
        $activationEnergy = $activationEnergyJPerMol
            ?? self::DEFAULT_ACTIVATION_ENERGY_J_PER_MOL;

        $this->validateActivationEnergy($activationEnergy);

        $exponentialTotal = 0.0;
        $validReadingCount = 0;

        foreach ($temperaturesCelsius as $temperatureCelsius) {
            /*
             * Null readings are ignored because telemetry may occasionally
             * arrive without a temperature value.
             */
            if ($temperatureCelsius === null || $temperatureCelsius === '') {
                continue;
            }

            if (!is_numeric($temperatureCelsius)) {
                throw new InvalidArgumentException(
                    'Every MKT temperature reading must be numeric.'
                );
            }

            $temperatureKelvin = $this->toKelvin(
                (float) $temperatureCelsius
            );

            $exponentialTotal += exp(
                -$activationEnergy /
                (
                    self::GAS_CONSTANT_J_PER_MOL_K
                    * $temperatureKelvin
                )
            );

            $validReadingCount++;
        }

        if ($validReadingCount === 0) {
            return null;
        }

        $averageExponential = $exponentialTotal / $validReadingCount;

        if ($averageExponential <= 0) {
            throw new InvalidArgumentException(
                'Unable to calculate MKT from the supplied readings.'
            );
        }

        $mktKelvin = -$activationEnergy /
            (
                self::GAS_CONSTANT_J_PER_MOL_K
                * log($averageExponential)
            );

        $mktCelsius = $mktKelvin - 273.15;

        return round($mktCelsius, $precision);
    }

    /**
     * Calculate MKT using time-weighted temperature readings.
     *
     * Recommended when telemetry readings are not recorded
     * at equal time intervals.
     *
     * Reading format:
     *
     * [
     *     [
     *         'temperature' => 4.5,
     *         'duration_seconds' => 300,
     *     ],
     * ]
     *
     * @param array<int, array{
     *     temperature: float|int|string|null,
     *     duration_seconds: float|int|string
     * }> $readings
     */
    public function calculateWeighted(
        array $readings,
        ?float $activationEnergyJPerMol = null,
        int $precision = 2
    ): ?float {
        $activationEnergy = $activationEnergyJPerMol
            ?? self::DEFAULT_ACTIVATION_ENERGY_J_PER_MOL;

        $this->validateActivationEnergy($activationEnergy);

        $weightedExponentialTotal = 0.0;
        $totalDurationSeconds = 0.0;

        foreach ($readings as $index => $reading) {
            $temperature = $reading['temperature'] ?? null;
            $duration = $reading['duration_seconds'] ?? null;

            if ($temperature === null || $temperature === '') {
                continue;
            }

            if (!is_numeric($temperature)) {
                throw new InvalidArgumentException(
                    "Temperature at reading index {$index} must be numeric."
                );
            }

            if (!is_numeric($duration) || (float) $duration <= 0) {
                throw new InvalidArgumentException(
                    "Duration at reading index {$index} must be greater than zero."
                );
            }

            $durationSeconds = (float) $duration;

            $temperatureKelvin = $this->toKelvin(
                (float) $temperature
            );

            $exponentialValue = exp(
                -$activationEnergy /
                (
                    self::GAS_CONSTANT_J_PER_MOL_K
                    * $temperatureKelvin
                )
            );

            $weightedExponentialTotal +=
                $durationSeconds * $exponentialValue;

            $totalDurationSeconds += $durationSeconds;
        }

        if ($totalDurationSeconds <= 0) {
            return null;
        }

        $weightedAverage =
            $weightedExponentialTotal / $totalDurationSeconds;

        if ($weightedAverage <= 0) {
            throw new InvalidArgumentException(
                'Unable to calculate weighted MKT.'
            );
        }

        $mktKelvin = -$activationEnergy /
            (
                self::GAS_CONSTANT_J_PER_MOL_K
                * log($weightedAverage)
            );

        return round($mktKelvin - 273.15, $precision);
    }

    /**
     * Calculate MKT directly from TelemetryLog models or arrays.
     *
     * @param iterable<mixed> $telemetryLogs
     */
    public function calculateFromTelemetry(
        iterable $telemetryLogs,
        ?float $activationEnergyJPerMol = null,
        int $precision = 2
    ): ?float {
        $temperatures = [];

        foreach ($telemetryLogs as $telemetryLog) {
            if (is_array($telemetryLog)) {
                $temperatures[] =
                    $telemetryLog['temperature'] ?? null;

                continue;
            }

            $temperatures[] = $telemetryLog->temperature ?? null;
        }

        return $this->calculate(
            temperaturesCelsius: $temperatures,
            activationEnergyJPerMol: $activationEnergyJPerMol,
            precision: $precision
        );
    }

    private function toKelvin(float $temperatureCelsius): float
    {
        $temperatureKelvin = $temperatureCelsius + 273.15;

        if ($temperatureKelvin <= 0) {
            throw new InvalidArgumentException(
                'Temperature cannot be equal to or below absolute zero.'
            );
        }

        return $temperatureKelvin;
    }

    private function validateActivationEnergy(
        float $activationEnergy
    ): void {
        if ($activationEnergy <= 0) {
            throw new InvalidArgumentException(
                'Activation energy must be greater than zero.'
            );
        }
    }
}