<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        if ($user->role === 'admin' || $trip->user_id === $user->id) {
            return true;
        }

        return $trip->driver_id !== null && Driver::query()
            ->whereKey($trip->driver_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['customer', 'admin'], true);
    }

    public function update(User $user, Trip $trip): bool
    {
        return $user->role === 'admin' ||
            $trip->user_id === $user->id ||
            ($trip->driver_id !== null && Driver::query()
                ->whereKey($trip->driver_id)
                ->where('user_id', $user->id)
                ->exists());
    }
}
