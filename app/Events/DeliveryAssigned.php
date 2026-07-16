<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Delivery $delivery, public int $driverUserId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('driver.status.'.$this->driverUserId)];
    }

    public function broadcastAs(): string
    {
        return 'DeliveryAssigned';
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'pickup_address' => $this->delivery->pickup_address,
            'dropoff_address' => $this->delivery->dropoff_address,
            'pickup_latitude' => $this->delivery->pickup_latitude,
            'pickup_longitude' => $this->delivery->pickup_longitude,
            'dropoff_latitude' => $this->delivery->dropoff_latitude,
            'dropoff_longitude' => $this->delivery->dropoff_longitude,
        ];
    }
}
