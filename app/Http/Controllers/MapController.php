<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Truck;
use App\Services\ColdChain\TemperatureStatusService;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    public function index(TemperatureStatusService $temperatureStatusService)
    {
        $snapshot = $this->fleetSnapshot($temperatureStatusService);

        return view('maps.index', [
            ...$snapshot,
            'googleMapsApiKey' => config('services.google_maps.key'),
            'mqttBroker' => config('services.hivemq.websocket_url'),
            'mqttUsername' => config('services.hivemq.username'),
            'mqttPassword' => config('services.hivemq.password'),
        ]);
    }

    public function latest(TemperatureStatusService $temperatureStatusService)
    {
        $snapshot = $this->fleetSnapshot($temperatureStatusService);

        return response()->json(['data' => $snapshot['mapTrips']])
            ->header('Cache-Control', 'no-store');
    }

    private function fleetSnapshot(TemperatureStatusService $temperatureStatusService): array
    {
        abort_unless(Auth::user()?->isAdministrator(), 403,
            'Only administrators can access live fleet monitoring.');

        $trips = Trip::with([
            'product',
            'driver',
            'receiver',
        ])
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->get();

        $trucks = Truck::with(['driver', 'devices.latestTelemetry', 'devices.latestGpsTelemetry'])
            ->orderBy('id')->get();

        $mapTrips = $trucks->map(function ($truck) use ($trips, $temperatureStatusService) {
            $trip = $trips->where('truck_id', $truck->id)
                ->sortBy(fn ($trip) => $trip->status === 'in_progress' ? 0 : 1)->first();
            $latest = $truck->devices->pluck('latestTelemetry')->filter()
                ->sortByDesc(fn ($reading) => $reading->recorded_at?->getTimestamp())->first();
            $gps = $truck->devices->pluck('latestGpsTelemetry')->filter()
                ->sortByDesc(fn ($reading) => $reading->recorded_at?->getTimestamp())->first();
            $gpsDevice = $gps ? $truck->devices->firstWhere('id', $gps->device_id) : null;
            $product = $trip?->product;
            return [
                'id' => $truck->id,
                'trip_id' => $trip?->id,
                'status' => $trip?->status ?? 'idle',

                'truck' => [
                    'id' => $truck->id,
                    'plate_number' => $truck->plate_number,
                    'model' => $truck->model,
                ],
                'devices' => $truck->devices->filter(fn ($device) =>
                    array_key_exists($device->device_code, config('coldtrace.devices', []))
                )->map(fn ($device) => [
                    'code' => $device->device_code,
                    'topic' => $device->mqtt_topic ?: config("coldtrace.devices.{$device->device_code}.mqtt_topic"),
                ])->values(),

                'product' => [
                    'name' => $product?->name,
                    'min_temp' => $product?->min_temp,
                    'max_temp' => $product?->max_temp,
                ],

                'driver' => [
                    'name' => $trip?->driver?->name ?? $truck->driver?->name,
                ],

                'receiver' => [
                    'name' => $trip?->receiver?->name,
                ],

                'origin' => [
                    'address' => $trip?->origin_address,
                    'lat' => $trip?->origin_lat,
                    'lng' => $trip?->origin_lng,
                ],

                'destination' => [
                    'address' => $trip?->destination_address,
                    'lat' => $trip?->destination_lat,
                    'lng' => $trip?->destination_lng,
                ],
                'gps' => $gps ? [
                    'lat' => (float) $gps->latitude,
                    'lng' => (float) $gps->longitude,
                    'recorded_at' => $gps->recorded_at?->toIso8601String(),
                    'source' => str_starts_with((string) $gpsDevice?->device_code, 'SIM-') ? 'demo' : 'sensor',
                ] : null,
                'latestTelemetry' => $latest ? [
                    'temperature' => $latest->temperature !== null ? (float) $latest->temperature : null,
                    'rsl_hours' => $trip && $latest->trip_id === $trip->id ? $latest->rsl_hours : null,
                    'recorded_at' => $latest->recorded_at?->toIso8601String(),
                    'temperature_status' => $temperatureStatusService->evaluate($latest->temperature, $product),
                ] : null,
            ];
        })->values();

        return compact('trips', 'mapTrips');
    }
}
