<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('driver.status.{driverId}', function ($user, $driverId) {
    return (int) $user->id === (int) $driverId;
});
