<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;

/**
 * Cards are visible to super-admin/staff in full; customers see and
 * act only on cards linked to their account.
 */
class CardPolicy
{
    /** Registry listing — everyone may list; the query scopes rows per role. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, Card $card): bool
    {
        if ($user->hasRole('customers')) {
            return (int) $card->user_id === (int) $user->id;
        }

        return $user->hasRole('super-admin', 'merchant', 'staff');
    }

    public function update(User $user, Card $card): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Card $card): bool
    {
        return $user->hasRole('super-admin');
    }

    public function requestChange(User $user, Card $card): bool
    {
        if ($user->hasRole('customers')) {
            return (int) $card->user_id === (int) $user->id;
        }

        return $user->hasRole('super-admin');
    }
}
