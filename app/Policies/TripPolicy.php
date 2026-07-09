<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, ['customer', 'admin'], true);
    }

    public function update(User $user, Trip $trip): bool
    {
        return $user->role === 'admin' || $trip->user_id === $user->id;
    }
}
