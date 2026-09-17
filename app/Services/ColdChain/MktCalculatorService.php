<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Throwable;

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
     * @param  array<int, float|int|string|null>  $temperaturesCelsius
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

            if (! is_numeric($temperatureCelsius)) {
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

            if (! is_numeric($temperature)) {
                throw new InvalidArgumentException(
                    "Temperature at reading index {$index} must be numeric."
                );
            }

            if (! is_numeric($duration) || (float) $duration <= 0) {
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
     * Telemetry timestamps are converted into trapezoidal time weights so
     * irregular upload intervals do not count every reading equally. If a
     * usable timestamp series is unavailable, the method safely falls back
     * to the equally spaced calculation.
     *
     * @param  iterable<mixed>  $telemetryLogs
     */
    public function calculateFromTelemetry(
        iterable $telemetryLogs,
        ?float $activationEnergyJPerMol = null,
        int $precision = 2
    ): ?float {
        $readings = [];

        foreach ($telemetryLogs as $telemetryLog) {
            if (is_array($telemetryLog)) {
                $temperature = $telemetryLog['temperature'] ?? null;
                $recordedAt = $telemetryLog['recorded_at'] ?? null;
            } else {
                $temperature = $telemetryLog->temperature ?? null;
                $recordedAt = $telemetryLog->recorded_at ?? null;
            }

            if ($temperature === null || $temperature === '') {
                continue;
            }

            $readings[] = [
                'temperature' => $temperature,
                'timestamp' => $this->timestamp($recordedAt),
            ];
        }

        if ($readings === []) {
            return null;
        }

        $temperatures = array_column($readings, 'temperature');
        $hasMissingTimestamp = false;

        foreach ($readings as $reading) {
            if ($reading['timestamp'] === null) {
                $hasMissingTimestamp = true;

                break;
            }
        }

        if (
            count($readings) < 2
            || $hasMissingTimestamp
        ) {
            return $this->calculate(
                temperaturesCelsius: $temperatures,
                activationEnergyJPerMol: $activationEnergyJPerMol,
                precision: $precision
            );
        }

        usort(
            $readings,
            fn (array $first, array $second): int => $first['timestamp'] <=> $second['timestamp']
        );

        for ($index = 1; $index < count($readings); $index++) {
            if (
                $readings[$index]['timestamp']
                <= $readings[$index - 1]['timestamp']
            ) {
                return $this->calculate(
                    temperaturesCelsius: array_column(
                        $readings,
                        'temperature'
                    ),
                    activationEnergyJPerMol: $activationEnergyJPerMol,
                    precision: $precision
                );
            }
        }

        $weightedReadings = [];
        $lastIndex = count($readings) - 1;

        foreach ($readings as $index => $reading) {
            if ($index === 0) {
                $durationSeconds =
                    ($readings[1]['timestamp'] - $reading['timestamp']) / 2;
            } elseif ($index === $lastIndex) {
                $durationSeconds =
                    ($reading['timestamp']
                        - $readings[$index - 1]['timestamp']) / 2;
            } else {
                $durationSeconds =
                    ($readings[$index + 1]['timestamp']
                        - $readings[$index - 1]['timestamp']) / 2;
            }

            $weightedReadings[] = [
                'temperature' => $reading['temperature'],
                'duration_seconds' => $durationSeconds,
            ];
        }

        return $this->calculateWeighted(
            readings: $weightedReadings,
            activationEnergyJPerMol: $activationEnergyJPerMol,
            precision: $precision
        );
    }

    private function timestamp(mixed $value): ?float
    {
        if ($value instanceof DateTimeInterface) {
            return (float) $value->format('U.u');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (float) CarbonImmutable::parse($value)->format('U.u');
        } catch (Throwable) {
            return null;
        }
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
