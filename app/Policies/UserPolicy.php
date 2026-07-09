<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewWallet(User $authenticatedUser, User $walletOwner): bool
    {
        return $authenticatedUser->role === 'admin' || $authenticatedUser->is($walletOwner);
    }

    public function requestRecharge(User $authenticatedUser, User $walletOwner): bool
    {
        return $authenticatedUser->is($walletOwner);
    }
}
