<?php

$devices = [];

foreach (range(1001, 1006) as $number) {
    $shortCode = "CT-{$number}";

    $devices["ESP32-{$shortCode}"] = [
        'device_code' => "ESP32-{$shortCode}",
        'mqtt_topic' => "coldtrace/trucks/{$shortCode}/telemetry",
        'truck_id' => env("COLDTRACE_DEVICE_{$number}_TRUCK_ID"),
    ];
}

return [
    'initial_admin' => [
        'name' => env('COLDTRACE_INITIAL_ADMIN_NAME'),
        'email' => env('COLDTRACE_INITIAL_ADMIN_EMAIL'),
        'password' => env('COLDTRACE_INITIAL_ADMIN_PASSWORD'),
    ],

    'telemetry' => [
        'subscription_topic' => env(
            'HIVEMQ_TELEMETRY_TOPIC',
            'coldtrace/trucks/+/telemetry'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | ColdTrace ESP32 Fleet
    |--------------------------------------------------------------------------
    |
    | Device codes must match the device_code value sent in each ESP32 JSON
    | payload. Topics use one MQTT level for the truck/device identifier so
    | the shared coldtrace/trucks/+/telemetry subscription receives all six.
    |
    */
    'devices' => $devices,
];
