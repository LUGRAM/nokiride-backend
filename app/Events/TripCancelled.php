<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Trip $trip,
        public int $driverId
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
            'status' => 'cancelled',
        ];
    }

    public function broadcastAs(): string
    {
        return 'TripCancelled';
    }
}
