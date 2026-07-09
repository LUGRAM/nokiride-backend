<?php

namespace App\Policies;

use App\Models\MarketOrder;
use App\Models\User;

class MarketOrderPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, ['customer', 'admin'], true);
    }

    public function view(User $user, MarketOrder $marketOrder): bool
    {
        return $user->role === 'admin' || $marketOrder->user_id === $user->id;
    }
}
