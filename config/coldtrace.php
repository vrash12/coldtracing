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
    'gps_timeout_seconds' => 30,
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
        'token' => env('COLDTRACE_TELEMETRY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MQTT Bridge
    |--------------------------------------------------------------------------
    |
    | The ESP32 fleet publishes to HiveMQ Cloud over TLS. `coldtrace:mqtt-listen`
    | subscribes with these settings and writes every reading into the database,
    | which is what makes alerts, MKT, shelf life, and the monitoring map work
    | with real hardware. The host is the same cluster address the sketch uses.
    |
    */
    'mqtt' => [
        'host' => env('HIVEMQ_HOST'),
        'port' => (int) env('HIVEMQ_PORT', 8883),
        'username' => env('HIVEMQ_USERNAME'),
        'password' => env('HIVEMQ_PASSWORD'),
        'tls' => (bool) env('HIVEMQ_TLS', true),

        /*
         * HiveMQ Cloud uses publicly trusted certificates, so verification is
         * on by default. Point this at a CA bundle if your PHP installation
         * cannot find one, or disable it only for a local test broker.
         */
        'verify_peer' => (bool) env('HIVEMQ_VERIFY_PEER', true),
        'ca_file' => env('HIVEMQ_CA_FILE'),

        'client_id' => env('HIVEMQ_CLIENT_ID', 'coldtrace-laravel-bridge'),
        'keep_alive' => (int) env('HIVEMQ_KEEP_ALIVE', 60),
        'qos' => (int) env('HIVEMQ_QOS', 1),
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
