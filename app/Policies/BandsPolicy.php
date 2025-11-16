<?php

namespace App\Policies;

use App\Models\Bands;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BandsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Bands $bands): bool
    {
        return $bands->owners->contains('user_id', $user->id)
            || $bands->members->contains('user_id', $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Bands $bands): bool
    {
        return $bands->owners->contains('user_id', $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Bands $bands): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Bands $bands): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Bands $bands): bool
    {
        return false;
    }
}
