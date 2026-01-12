<?php

namespace App\Policies;

use App\Models\Events;
use App\Models\User;
use App\Models\Bands;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventsPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Bands $band)
    {
        return $band->owners->contains('user_id', $user->id) || $band->members->contains('user_id', $user->id);
    }

    public function view(User $user, Events $event)
    {
        $band = $event->eventable->band;
        return $this->viewAny($user, $band);
    }

    public function create(User $user, Bands $band)
    {
        return $this->viewAny($user, $band);
    }

    public function update(User $user, Events $event)
    {
        $band = $event->eventable->band;
        return $this->viewAny($user, $band);
    }

    public function delete(User $user, Events $event)
    {
        $band = $event->eventable->band;
        return $band->owners->contains('user_id', $user->id);
    }
}
