<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, ['customer', 'admin'], true);
    }

    public function update(User $user, Delivery $delivery): bool
    {
        return $user->role === 'admin' || $delivery->user_id === $user->id;
    }
}
