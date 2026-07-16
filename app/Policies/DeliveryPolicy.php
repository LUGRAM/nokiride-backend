<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\User;

class DeliveryPolicy
{
    public function view(User $user, Delivery $delivery): bool
    {
        return $user->role === 'admin' ||
            $delivery->user_id === $user->id ||
            ($delivery->driver_id !== null && Driver::query()
                ->whereKey($delivery->driver_id)
                ->where('user_id', $user->id)
                ->exists());
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['customer', 'admin'], true);
    }

    public function update(User $user, Delivery $delivery): bool
    {
        return $this->view($user, $delivery);
    }
}
