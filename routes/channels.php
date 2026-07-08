<?php

use Illuminate\Support\Facades\Broadcast;


/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('setlist.{sessionId}', function ($user, $sessionId) {
    $session = \App\Models\LiveSetlistSession::find($sessionId);
    if (!$session) return false;
    return $user->canRead('events', $session->band_id);
});

Broadcast::channel('rehearsal-planner.{sessionId}', function ($user, $sessionId) {
    $session = \App\Models\RehearsalPlannerSession::find($sessionId);
    if (!$session) {
        return false;
    }
    return $user->canRead('rehearsals', $session->band_id);
});

// Generic band data channel: thin BandDataChanged invalidation signals.
// Same audience idiom as the setlist/planner channels — any user who can
// read the band's events (owners, members, subs). Signals carry no data;
// the API enforces per-resource permissions on the refetch.
//
// Deliberate trade-off (see the mobile repo's realtime spec): one shared
// channel per band means subs also receive thin signals for models they
// cannot read (bookings/rehearsal/roster) — existence + numeric id only,
// never fields. Their refetch is still denied by the API. Splitting into
// per-ability channels would multiply subscriptions for no data-level gain.
Broadcast::channel('band.{bandId}', function ($user, $bandId) {
    return $user->canRead('events', (int) $bandId);
});
