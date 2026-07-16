<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Trip $trip,
        public array $location,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('trip.'.$this->trip->id)];
    }

    public function broadcastAs(): string
    {
        return 'DriverLocationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'trip_status' => $this->trip->status,
            'latitude' => (float) $this->location['latitude'],
            'longitude' => (float) $this->location['longitude'],
            'heading' => isset($this->location['heading']) ? (float) $this->location['heading'] : null,
            'speed' => isset($this->location['speed']) ? (float) $this->location['speed'] : null,
            'accuracy' => isset($this->location['accuracy']) ? (float) $this->location['accuracy'] : null,
            'recorded_at' => $this->location['recorded_at'],
        ];
    }
}
