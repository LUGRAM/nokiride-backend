<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Trip $trip,
        public int $driverId,
        public int $timeoutSeconds = 15
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('driver.status.' . $this->driverId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'pickup_address' => $this->trip->pickup_address,
            'destination_address' => $this->trip->dropoff_address,
            'estimated_earnings' => $this->trip->price_fcfa,
            'distance' => $this->trip->distance_km,
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }

    public function broadcastAs(): string
    {
        return 'TripRequested';
    }
}
