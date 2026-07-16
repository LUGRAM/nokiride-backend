<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Trip;
use App\Models\Delivery;

Broadcast::channel('driver.status.{driverId}', function ($user, $driverId) {
    return (int) $user->id === (int) $driverId;
});

Broadcast::channel('delivery.{deliveryId}', function ($user, $deliveryId) {
    $delivery = Delivery::query()->with('driver')->find($deliveryId);

    return $delivery && (
        (int) $delivery->user_id === (int) $user->id ||
        (int) ($delivery->driver?->user_id ?? 0) === (int) $user->id
    );
});

Broadcast::channel('trip.{tripId}', function ($user, $tripId) {
    $trip = Trip::query()->with('driver')->find($tripId);

    return $trip && (
        (int) $trip->user_id === (int) $user->id ||
        (int) ($trip->driver?->user_id ?? 0) === (int) $user->id
    );
});
