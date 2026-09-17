<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use App\Models\Alert;
use App\Models\TelemetryLog;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TemperatureAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

final class TemperatureAlertService
{
    private const ALERT_TYPES = [
        'temperature_too_low',
        'temperature_too_high',
    ];

    public function __construct(
        private readonly TemperatureStatusService $temperatureStatus
    ) {}

    /**
     * Synchronize the open temperature alert with the latest trip reading.
     *
     * The caller runs this inside the telemetry database transaction. Locking
     * the trip serializes concurrent readings and prevents duplicate alerts.
     *
     * @return array<string, mixed>
     */
    public function synchronize(
        Trip $trip,
        TelemetryLog $telemetryLog
    ): array {
        Trip::query()
            ->whereKey($trip->id)
            ->lockForUpdate()
            ->firstOrFail();

        $trip->loadMissing([
            'product',
            'driver',
            'receiver',
        ]);

        $state = $this->temperatureStatus->evaluate(
            $telemetryLog->temperature,
            $trip->product
        );

        if ($state['code'] === TemperatureStatusService::NO_DATA) {
            return $state;
        }

        $openAlerts = Alert::query()
            ->where('trip_id', $trip->id)
            ->whereIn('type', self::ALERT_TYPES)
            ->where('is_resolved', false)
            ->lockForUpdate()
            ->get();

        if ($state['code'] === TemperatureStatusService::SAFE) {
            $this->resolveAlerts($trip, $openAlerts);

            return $state;
        }

        $targetType = $state['code'] === TemperatureStatusService::TOO_LOW
            ? 'temperature_too_low'
            : 'temperature_too_high';

        $this->resolveAlerts(
            $trip,
            $openAlerts->where('type', '!=', $targetType)
        );

        $matchingAlerts = $openAlerts
            ->where('type', $targetType)
            ->values();

        /** @var Alert|null $activeAlert */
        $activeAlert = $matchingAlerts->shift();

        if ($matchingAlerts->isNotEmpty()) {
            $this->resolveAlerts($trip, $matchingAlerts);
        }

        $severity = $state['code'] === TemperatureStatusService::TOO_HIGH
            ? 'critical'
            : 'warning';

        $message = $this->breachMessage($trip, $state);

        if ($activeAlert) {
            $activeAlert->update([
                'telemetry_log_id' => $telemetryLog->id,
                'severity' => $severity,
                'message' => $message,
            ]);

            return [
                ...$state,
                'alert_id' => $activeAlert->id,
                'alert_created' => false,
            ];
        }

        $activeAlert = Alert::create([
            'trip_id' => $trip->id,
            'telemetry_log_id' => $telemetryLog->id,
            'type' => $targetType,
            'severity' => $severity,
            'message' => $message,
            'is_resolved' => false,
            'resolved_at' => null,
        ]);

        $this->notifyTripUsers($trip, $activeAlert, 'opened');

        return [
            ...$state,
            'alert_id' => $activeAlert->id,
            'alert_created' => true,
        ];
    }

    /**
     * @param  Collection<int, Alert>  $alerts
     */
    private function resolveAlerts(Trip $trip, Collection $alerts): void
    {
        foreach ($alerts as $alert) {
            $alert->update([
                'is_resolved' => true,
                'resolved_at' => now(),
            ]);

            $this->notifyTripUsers(
                $trip,
                $alert->fresh(),
                'resolved'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function breachMessage(Trip $trip, array $state): string
    {
        $comparison = $state['code'] === TemperatureStatusService::TOO_LOW
            ? 'below'
            : 'above';

        return sprintf(
            '%s temperature is %.2f °C, %s the safe range of %.2f–%.2f °C.',
            $trip->product?->name ?? 'Cargo',
            $state['temperature'],
            $comparison,
            $state['minimum'],
            $state['maximum']
        );
    }

    private function notifyTripUsers(
        Trip $trip,
        Alert $alert,
        string $event
    ): void {
        $administrators = User::query()
            ->where('status', 'active')
            ->whereHas('role', function ($query) {
                $query->where('name', 'Administrator');
            })
            ->get();

        $recipients = collect([
            $trip->driver,
            $trip->receiver,
        ])
            ->merge($administrators)
            ->filter(fn (?User $user): bool => $user !== null && $user->status === 'active')
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new TemperatureAlertNotification($alert, $event)
        );
    }
}
