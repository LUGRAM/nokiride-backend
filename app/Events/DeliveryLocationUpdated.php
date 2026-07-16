<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Delivery $delivery, public array $location) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('delivery.'.$this->delivery->id)];
    }

    public function broadcastAs(): string
    {
        return 'DeliveryLocationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'delivery_status' => $this->delivery->status,
            'latitude' => (float) $this->location['latitude'],
            'longitude' => (float) $this->location['longitude'],
            'heading' => isset($this->location['heading']) ? (float) $this->location['heading'] : null,
            'speed' => isset($this->location['speed']) ? (float) $this->location['speed'] : null,
            'accuracy' => isset($this->location['accuracy']) ? (float) $this->location['accuracy'] : null,
            'recorded_at' => $this->location['recorded_at'],
        ];
    }
}
