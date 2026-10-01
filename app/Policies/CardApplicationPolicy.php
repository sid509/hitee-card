<?php

namespace App\Policies;

use App\Models\CardApplication;
use App\Models\User;

/**
 * Card applications: customers submit and view their own requests;
 * super-admins review and approve/reject them.
 */
class CardApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, CardApplication $cardApplication): bool
    {
        if ($user->hasRole('customers')) {
            return (int) $cardApplication->user_id === (int) $user->id;
        }

        return $user->hasRole('super-admin');
    }

    public function update(User $user, CardApplication $cardApplication): bool
    {
        return $user->hasRole('super-admin');
    }
}
