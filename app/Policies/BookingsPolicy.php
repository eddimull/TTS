<?php

namespace App\Policies;

use App\Models\Bookings;
use App\Models\User;
use App\Models\Bands;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingsPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Bands $band)
    {
        return $band->owners->contains('user_id', $user->id) || $band->members->contains('user_id', $user->id);
    }

    public function view(User $user, Bookings $booking)
    {
        return $this->viewAny($user, $booking->band);
    }

    public function create(User $user, Bands $band)
    {
        return $this->viewAny($user, $band);
    }

    public function store(User $user, Bands $band)
    {
        return $band->owners->contains('user_id', $user->id) || $user->permissionsForBand($band->id)->write_bookings;
    }

    public function update(User $user, Bookings $booking)
    {
        return $this->viewAny($user, $booking->band);
    }

    public function delete(User $user, Bookings $booking)
    {
        return $this->viewAny($user, $booking->band);
    }
}
