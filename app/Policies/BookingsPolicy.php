<?php

namespace App\Policies;

use App\Models\Bookings;
use App\Models\User;
use App\Models\Bands;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingsPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability, ...$arguments): ?bool
    {
        $band = null;
        foreach ($arguments as $argument) {
            if ($argument instanceof Bands) {
                $band = $argument;
                break;
            }
        }
        if (!$band) {
            if ($user->bandOwner->count() > 0) {
                // the user owns at least one band, so they can see bookings
                // this does mean the user can see bookings for all bands they have access to,
                // because of the way bookings are listed for all bands
                // this should ONLY fire for listing bookings, because all the other routes have a band parameter
                return true;
            }
        }

        if (!$band) {
            return null;
        }

        if ($user->ownsBand($band->id)) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('read_bookings');
    }

    public function view(User $user): bool
    {
        return $user->can('read_bookings');
    }

    public function create(User $user): bool
    {
        return $user->can('write_bookings');
    }

    public function store(User $user): bool
    {
        return $user->can('write_bookings');
    }

    public function update(User $user): bool
    {
        return $user->can('write_bookings');
    }

    public function delete(User $user): bool
    {
        return $user->can('write_bookings');
    }
}
