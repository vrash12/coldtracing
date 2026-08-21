<?php

namespace Tests\Feature;

use Database\Seeders\ColdTraceDeviceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ColdTraceDeviceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_six_esp32_devices_have_the_expected_codes_and_topics(): void
    {
        $devices = config('coldtrace.devices');

        $this->assertCount(6, $devices);

        foreach (range(1001, 1006) as $number) {
            $deviceCode = "ESP32-CT-{$number}";

            $this->assertSame($deviceCode, $devices[$deviceCode]['device_code']);
            $this->assertSame(
                "coldtrace/trucks/CT-{$number}/telemetry",
                $devices[$deviceCode]['mqtt_topic']
            );
        }
    }

    public function test_the_telemetry_api_route_is_registered(): void
    {
        $this->assertTrue(Route::has('api.telemetry.store'));
        $this->assertSame(
            'api/telemetry',
            Route::getRoutes()->getByName('api.telemetry.store')->uri()
        );
    }

    public function test_the_device_seeder_provisions_all_six_devices(): void
    {
        $this->seed(ColdTraceDeviceSeeder::class);

        $this->assertDatabaseCount('devices', 6);

        foreach (range(1001, 1006) as $number) {
            $this->assertDatabaseHas('devices', [
                'device_code' => "ESP32-CT-{$number}",
                'mqtt_topic' => "coldtrace/trucks/CT-{$number}/telemetry",
                'status' => 'inactive',
            ]);
        }
    }

    public function test_telemetry_rejects_a_device_outside_the_configured_fleet(): void
    {
        $response = $this->postJson('/api/telemetry', [
            'device_code' => 'ESP32-CT-9999',
            'temperature' => 4.5,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_code');
    }
}
