<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Product;

final class TemperatureStatusService
{
    public const NO_DATA = 'no_data';

    public const SAFE = 'safe';

    public const TOO_LOW = 'too_low';

    public const TOO_HIGH = 'too_high';

    /**
     * Compare a temperature reading with its product's validated limits.
     *
     * @return array{
     *     code: string,
     *     label: string,
     *     class: string,
     *     icon: string,
     *     temperature: float|null,
     *     minimum: float|null,
     *     maximum: float|null,
     *     is_breach: bool
     * }
     */
    public function evaluate(
        mixed $temperature,
        ?Product $product
    ): array {
        $minimum = $product?->min_temp;
        $maximum = $product?->max_temp;

        if (
            ! is_numeric($temperature)
            || ! is_numeric($minimum)
            || ! is_numeric($maximum)
            || ! is_finite((float) $temperature)
            || ! is_finite((float) $minimum)
            || ! is_finite((float) $maximum)
            || (float) $minimum > (float) $maximum
        ) {
            return $this->state(
                code: self::NO_DATA,
                label: 'No Data',
                class: 'neutral',
                icon: 'bi-dash-circle',
                temperature: is_numeric($temperature)
                    ? (float) $temperature
                    : null,
                minimum: is_numeric($minimum) ? (float) $minimum : null,
                maximum: is_numeric($maximum) ? (float) $maximum : null,
                isBreach: false
            );
        }

        $temperature = (float) $temperature;
        $minimum = (float) $minimum;
        $maximum = (float) $maximum;

        if ($temperature < $minimum) {
            return $this->state(
                code: self::TOO_LOW,
                label: 'Too Low',
                class: 'warning',
                icon: 'bi-thermometer-low',
                temperature: $temperature,
                minimum: $minimum,
                maximum: $maximum,
                isBreach: true
            );
        }

        if ($temperature > $maximum) {
            return $this->state(
                code: self::TOO_HIGH,
                label: 'Too High',
                class: 'critical',
                icon: 'bi-thermometer-high',
                temperature: $temperature,
                minimum: $minimum,
                maximum: $maximum,
                isBreach: true
            );
        }

        return $this->state(
            code: self::SAFE,
            label: 'Safe',
            class: 'safe',
            icon: 'bi-shield-check',
            temperature: $temperature,
            minimum: $minimum,
            maximum: $maximum,
            isBreach: false
        );
    }

    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     class: string,
     *     icon: string,
     *     temperature: float|null,
     *     minimum: float|null,
     *     maximum: float|null,
     *     is_breach: bool
     * }
     */
    private function state(
        string $code,
        string $label,
        string $class,
        string $icon,
        ?float $temperature,
        ?float $minimum,
        ?float $maximum,
        bool $isBreach
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'class' => $class,
            'icon' => $icon,
            'temperature' => $temperature,
            'minimum' => $minimum,
            'maximum' => $maximum,
            'is_breach' => $isBreach,
        ];
    }
}
