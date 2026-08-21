<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Product;
use InvalidArgumentException;

final class RemainingShelfLifeService
{
    /**
     * Calculate remaining shelf life using an Arrhenius-based
     * temperature acceleration factor.
     *
     * Acceleration factor:
     *
     * AF = exp[
     *     (Ea / R)
     *     ×
     *     (
     *         1 / T_reference
     *         -
     *         1 / T_mkt
     *     )
     * ]
     *
     * Effective age:
     *
     * Effective Age = Elapsed Hours × AF
     *
     * Remaining Shelf Life:
     *
     * RSL = Initial Shelf Life - Effective Age
     *
     * @return array{
     *     initial_shelf_life_hours: float,
     *     elapsed_hours: float,
     *     effective_age_hours: float,
     *     remaining_hours: float,
     *     remaining_percentage: float,
     *     mkt_celsius: float,
     *     reference_temperature_celsius: float,
     *     acceleration_factor: float,
     *     activation_energy_j_per_mol: float,
     *     status: string,
     *     is_expired: bool
     * }
     */
    public function calculate(
        float $initialShelfLifeHours,
        float $elapsedHours,
        float $mktCelsius,
        float $referenceTemperatureCelsius,
        ?float $activationEnergyJPerMol = null,
        int $precision = 2
    ): array {
        $activationEnergy = $activationEnergyJPerMol
            ?? MktCalculatorService::DEFAULT_ACTIVATION_ENERGY_J_PER_MOL;

        $this->validateInputs(
            initialShelfLifeHours: $initialShelfLifeHours,
            elapsedHours: $elapsedHours,
            activationEnergy: $activationEnergy
        );

        $mktKelvin = $this->toKelvin($mktCelsius);

        $referenceKelvin = $this->toKelvin(
            $referenceTemperatureCelsius
        );

        $exponent =
            (
                $activationEnergy /
                MktCalculatorService::GAS_CONSTANT_J_PER_MOL_K
            )
            *
            (
                (1 / $referenceKelvin)
                -
                (1 / $mktKelvin)
            );

        /*
         * PHP exp() overflows at extremely large exponents.
         * An exponent this high normally indicates invalid scientific
         * configuration or unrealistic temperature data.
         */
        if ($exponent > 700) {
            throw new InvalidArgumentException(
                'The calculated shelf-life acceleration factor is too large. Check the MKT, reference temperature, and activation energy.'
            );
        }

        $accelerationFactor = exp($exponent);

        $effectiveAgeHours =
            $elapsedHours * $accelerationFactor;

        $remainingHours = max(
            0,
            $initialShelfLifeHours - $effectiveAgeHours
        );

        $remainingPercentage = max(
            0,
            min(
                100,
                ($remainingHours / $initialShelfLifeHours) * 100
            )
        );

        return [
            'initial_shelf_life_hours' => round(
                $initialShelfLifeHours,
                $precision
            ),

            'elapsed_hours' => round(
                $elapsedHours,
                $precision
            ),

            'effective_age_hours' => round(
                $effectiveAgeHours,
                $precision
            ),

            'remaining_hours' => round(
                $remainingHours,
                $precision
            ),

            'remaining_percentage' => round(
                $remainingPercentage,
                $precision
            ),

            'mkt_celsius' => round(
                $mktCelsius,
                $precision
            ),

            'reference_temperature_celsius' => round(
                $referenceTemperatureCelsius,
                $precision
            ),

            'acceleration_factor' => round(
                $accelerationFactor,
                4
            ),

            'activation_energy_j_per_mol' => round(
                $activationEnergy,
                2
            ),

            'status' => $this->resolveStatus(
                $remainingPercentage
            ),

            'is_expired' => $remainingHours <= 0,
        ];
    }

    /**
     * Calculate RSL using your Product model.
     *
     * Expected Product fields:
     *
     * - initial_shelf_life_hours
     * - min_temp
     * - max_temp
     *
     * Optional recommended Product fields:
     *
     * - reference_storage_temp_celsius
     * - activation_energy_j_per_mol
     *
     * When reference_storage_temp_celsius is unavailable,
     * the midpoint between min_temp and max_temp is used.
     *
     * @return array<string, float|string|bool>
     */
    public function calculateForProduct(
        Product $product,
        float $elapsedHours,
        float $mktCelsius,
        ?float $referenceTemperatureCelsius = null,
        ?float $activationEnergyJPerMol = null
    ): array {
        $initialShelfLife = data_get(
            $product,
            'initial_shelf_life_hours'
        );

        if (
            $initialShelfLife === null
            || !is_numeric($initialShelfLife)
        ) {
            throw new InvalidArgumentException(
                'The product does not have a valid initial_shelf_life_hours value.'
            );
        }

        $referenceSource = 'method_argument';

        if ($referenceTemperatureCelsius === null) {
            $storedReferenceTemperature = data_get(
                $product,
                'reference_storage_temp_celsius'
            );

            if (is_numeric($storedReferenceTemperature)) {
                $referenceTemperatureCelsius =
                    (float) $storedReferenceTemperature;

                $referenceSource =
                    'product.reference_storage_temp_celsius';
            } else {
                $minimumTemperature = data_get(
                    $product,
                    'min_temp'
                );

                $maximumTemperature = data_get(
                    $product,
                    'max_temp'
                );

                if (
                    !is_numeric($minimumTemperature)
                    || !is_numeric($maximumTemperature)
                ) {
                    throw new InvalidArgumentException(
                        'The product must have valid min_temp and max_temp values when no reference temperature is provided.'
                    );
                }

                /*
                 * This midpoint is a fallback assumption.
                 * A validated product reference-storage temperature
                 * is more scientifically appropriate.
                 */
                $referenceTemperatureCelsius =
                    (
                        (float) $minimumTemperature
                        +
                        (float) $maximumTemperature
                    ) / 2;

                $referenceSource =
                    'safe_temperature_range_midpoint';
            }
        }

        $activationEnergySource = 'method_argument';

        if ($activationEnergyJPerMol === null) {
            $storedActivationEnergy = data_get(
                $product,
                'activation_energy_j_per_mol'
            );

            if (is_numeric($storedActivationEnergy)) {
                $activationEnergyJPerMol =
                    (float) $storedActivationEnergy;

                $activationEnergySource =
                    'product.activation_energy_j_per_mol';
            } else {
                $activationEnergyJPerMol =
                    MktCalculatorService::DEFAULT_ACTIVATION_ENERGY_J_PER_MOL;

                $activationEnergySource =
                    'default_83144_j_per_mol';
            }
        }

        $result = $this->calculate(
            initialShelfLifeHours: (float) $initialShelfLife,
            elapsedHours: $elapsedHours,
            mktCelsius: $mktCelsius,
            referenceTemperatureCelsius:
                $referenceTemperatureCelsius,
            activationEnergyJPerMol:
                $activationEnergyJPerMol
        );

        $result['reference_temperature_source'] =
            $referenceSource;

        $result['activation_energy_source'] =
            $activationEnergySource;

        return $result;
    }

    private function resolveStatus(
        float $remainingPercentage
    ): string {
        return match (true) {
            $remainingPercentage <= 0 => 'expired',
            $remainingPercentage <= 10 => 'critical',
            $remainingPercentage <= 30 => 'high_risk',
            $remainingPercentage <= 60 => 'moderate',
            default => 'good',
        };
    }

    private function validateInputs(
        float $initialShelfLifeHours,
        float $elapsedHours,
        float $activationEnergy
    ): void {
        if ($initialShelfLifeHours <= 0) {
            throw new InvalidArgumentException(
                'Initial shelf life must be greater than zero.'
            );
        }

        if ($elapsedHours < 0) {
            throw new InvalidArgumentException(
                'Elapsed hours cannot be negative.'
            );
        }

        if ($activationEnergy <= 0) {
            throw new InvalidArgumentException(
                'Activation energy must be greater than zero.'
            );
        }
    }

    private function toKelvin(
        float $temperatureCelsius
    ): float {
        $temperatureKelvin =
            $temperatureCelsius + 273.15;

        if ($temperatureKelvin <= 0) {
            throw new InvalidArgumentException(
                'Temperature cannot be equal to or below absolute zero.'
            );
        }

        return $temperatureKelvin;
    }
}