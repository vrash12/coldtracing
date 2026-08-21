<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    public function index()
    {
        if (! Auth::user()?->isAdministrator()) {
            abort(403, 'Only administrators can access live fleet monitoring.');
        }

        $trips = Trip::with([
            'truck',
            'product',
            'driver',
            'receiver',
            'latestTelemetry',
        ])
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->get();

        $mapTrips = $trips->map(function ($trip) {
            return [
                'id' => $trip->id,
                'status' => $trip->status,

                'truck' => [
                    'plate_number' => optional($trip->truck)->plate_number,
                    'model' => optional($trip->truck)->model,
                ],

                'product' => [
                    'name' => optional($trip->product)->name,
                    'min_temp' => optional($trip->product)->min_temp,
                    'max_temp' => optional($trip->product)->max_temp,
                ],

                'driver' => [
                    'name' => optional($trip->driver)->name,
                ],

                'receiver' => [
                    'name' => optional($trip->receiver)->name,
                ],

                'origin' => [
                    'address' => $trip->origin_address,
                    'lat' => $trip->origin_lat ? (float) $trip->origin_lat : null,
                    'lng' => $trip->origin_lng ? (float) $trip->origin_lng : null,
                ],

                'destination' => [
                    'address' => $trip->destination_address,
                    'lat' => $trip->destination_lat ? (float) $trip->destination_lat : null,
                    'lng' => $trip->destination_lng ? (float) $trip->destination_lng : null,
                ],

                'latestTelemetry' => $trip->latestTelemetry ? [
                    'lat' => $trip->latestTelemetry->latitude ? (float) $trip->latestTelemetry->latitude : null,
                    'lng' => $trip->latestTelemetry->longitude ? (float) $trip->latestTelemetry->longitude : null,
                    'temperature' => (float) $trip->latestTelemetry->temperature,
                    'humidity' => $trip->latestTelemetry->humidity,
                    'rsl_hours' => $trip->latestTelemetry->rsl_hours,
                    'recorded_at' => optional($trip->latestTelemetry->recorded_at)->format('Y-m-d H:i:s'),
                ] : null,
            ];
        })->values();

        return view('maps.index', [
            'trips' => $trips,
            'mapTrips' => $mapTrips,
            'googleMapsApiKey' => config('services.google_maps.key'),
        ]);
    }
}
