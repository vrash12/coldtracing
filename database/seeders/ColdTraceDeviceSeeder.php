<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Truck;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class ColdTraceDeviceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('coldtrace.devices', []) as $definition) {
            $device = Device::firstOrNew([
                'device_code' => $definition['device_code'],
            ]);

            $device->mqtt_topic = $definition['mqtt_topic'];

            if (! $device->exists) {
                $device->status = 'inactive';
            }

            $configuredTruckId = $definition['truck_id'] ?? null;

            if ($configuredTruckId !== null && $configuredTruckId !== '') {
                if (! Truck::whereKey($configuredTruckId)->exists()) {
                    throw new InvalidArgumentException(
                        "Truck {$configuredTruckId} configured for {$definition['device_code']} does not exist."
                    );
                }

                $device->truck_id = (int) $configuredTruckId;
            }

            $device->save();
        }
    }
}
