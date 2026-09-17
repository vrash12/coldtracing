<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\TelemetryLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The single entry point for a device reading, whichever transport carried it.
 *
 * ColdTrace accepts telemetry over HTTP and over MQTT. Both arrive here so the
 * validation rules and the GPS quality gate cannot drift apart between them.
 */
final class TelemetryIngestionService
{
    public function __construct(
        private readonly TelemetryProcessingService $processor
    ) {}

    /**
     * Validation rules shared by every transport.
     *
     * Temperature must be present but may be null: the ESP32 sketch publishes a
     * reading with a null temperature when its probe is unavailable, and losing
     * the truck's position during a sensor fault would be worse than recording
     * the position without a temperature.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'device_code' => [
                'required',
                'string',
                'max:255',
                Rule::in(array_keys(config('coldtrace.devices', []))),
            ],

            'temperature' => [
                'present',
                'nullable',
                'numeric',
                'between:-100,100',
            ],

            'latitude' => [
                'nullable',
                'required_with:longitude',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'required_with:latitude',
                'numeric',
                'between:-180,180',
            ],

            'gps_valid' => ['nullable', 'boolean'],
            'satellites' => ['nullable', 'integer', 'between:0,100'],
            'hdop' => ['nullable', 'numeric', 'between:0,99.9'],
            'humidity' => ['nullable', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Validate a raw payload and return only the fields ColdTrace stores.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $payload): array
    {
        return Validator::make($payload, self::rules())->validate();
    }

    /**
     * Drop coordinates the device itself reported as unreliable.
     *
     * A poor fix is worse than no fix: it would place the truck somewhere it has
     * never been. The temperature in the same reading is still kept.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function discardPoorQualityPosition(array $validated): array
    {
        $qualityIsPoor = ($validated['gps_valid'] ?? true) === false
            || (isset($validated['satellites']) && $validated['satellites'] < 4)
            || (isset($validated['hdop']) && $validated['hdop'] > 5);

        if ($qualityIsPoor) {
            $validated['latitude'] = null;
            $validated['longitude'] = null;
        }

        return $validated;
    }

    /**
     * Validate, clean, and store one reading, recalculating the trip's
     * cold-chain figures and alert state.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function ingest(array $payload): TelemetryLog
    {
        $validated = $this->discardPoorQualityPosition(
            $this->validate($payload)
        );

        return $this->processor->process($validated);
    }
}
