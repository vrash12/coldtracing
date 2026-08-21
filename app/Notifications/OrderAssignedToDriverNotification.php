<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderAssignedToDriverNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->order->loadMissing([
            'receiver',
            'driver',
            'orderItems.product',
        ]);

        return [
            'type' => 'order_assigned',
            'title' => 'New Assigned Order',
            'message' => 'You have been assigned to order ' . $this->order->order_code . '.',
            'order_id' => $this->order->id,
            'order_code' => $this->order->order_code,
            'receiver_name' => $this->order->receiver?->name,
            'receiver_phone' => $this->order->receiver?->phone,
            'delivery_address' => $this->order->delivery_address,
            'delivery_lat' => $this->order->delivery_lat,
            'delivery_lng' => $this->order->delivery_lng,
            'expected_delivery_at' => optional($this->order->expected_delivery_at)->format('M d, Y h:i A'),
            'products' => $this->order->orderItems
                ->map(function ($item) {
                    return [
                        'name' => $item->product?->name ?? 'N/A',
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }
}