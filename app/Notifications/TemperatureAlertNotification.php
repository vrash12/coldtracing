<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TemperatureAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Alert $alert,
        public string $event
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->alert->loadMissing([
            'trip.order',
            'trip.product',
            'trip.truck',
        ]);

        $resolved = $this->event === 'resolved';
        $trip = $this->alert->trip;

        return [
            'type' => 'temperature_alert',
            'event' => $this->event,
            'title' => $resolved
                ? 'Temperature Recovered'
                : 'Temperature Alert',
            'message' => $resolved
                ? 'The cargo temperature returned to the safe range.'
                : $this->alert->message,
            'alert_id' => $this->alert->id,
            'trip_id' => $this->alert->trip_id,
            'order_id' => $trip?->order_id,
            'order_code' => $trip?->order?->order_code,
            'product_name' => $trip?->product?->name,
            'truck_plate_number' => $trip?->truck?->plate_number,
            'alert_type' => $this->alert->type,
            'severity' => $this->alert->severity,
            'is_resolved' => $this->alert->is_resolved,
            'resolved_at' => $this->alert->resolved_at?->toIso8601String(),
        ];
    }
}
