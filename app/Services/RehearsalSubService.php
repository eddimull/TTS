<?php

namespace App\Services;

use App\Jobs\ProcessRehearsalSubAdded;
use App\Jobs\ProcessRehearsalSubRemoved;
use App\Models\BandSubs;
use App\Models\Rehearsal;
use App\Models\RehearsalSub;
use App\Models\SubstituteCallList;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RehearsalSubService
{
    /**
     * Attach a substitute to a rehearsal (assignment + notification model —
     * no accept step). $data is either ['call_list_entry_id' => int] or
     * ['name', 'email', 'phone'?, 'band_role_id'?].
     *
     * @throws ValidationException on cancelled/past rehearsal, duplicate, or
     *         a call-list entry with no email.
     */
    public function invite(Rehearsal $rehearsal, User $actor, array $data): RehearsalSub
    {
        $band  = $rehearsal->rehearsalSchedule?->band ?? $rehearsal->band;
        $event = $rehearsal->events->first();
        $date  = $event
            ? (is_string($event->date) ? $event->date : $event->date->format('Y-m-d'))
            : null;

        if ($rehearsal->is_cancelled) {
            throw ValidationException::withMessages([
                'rehearsal' => 'This rehearsal has been cancelled.',
            ]);
        }
        if ($date !== null && $date < now()->toDateString()) {
            throw ValidationException::withMessages([
                'rehearsal' => 'This rehearsal has already happened.',
            ]);
        }

        if (isset($data['call_list_entry_id'])) {
            $entry = SubstituteCallList::where('band_id', $band->id)
                ->findOrFail($data['call_list_entry_id']);

            $name   = $entry->display_name;
            $email  = $entry->display_email;
            $phone  = $entry->display_phone;
            $roleId = $entry->band_role_id;
            $userId = $entry->rosterMember?->user_id;
        } else {
            $name   = $data['name'];
            $email  = $data['email'];
            $phone  = $data['phone'] ?? null;
            $roleId = $data['band_role_id'] ?? null;
            $userId = null;

            // band_role_id is only validated as exists:band_roles,id, so a
            // client could otherwise attach another band's role.
            if ($roleId !== null && !\App\Models\BandRole::where('band_id', $band->id)->whereKey($roleId)->exists()) {
                throw ValidationException::withMessages([
                    'band_role_id' => 'That role does not belong to this band.',
                ]);
            }
        }

        if (!$email) {
            throw ValidationException::withMessages([
                'email' => 'This call list entry has no email address — add one to the call list first.',
            ]);
        }

        $userId ??= User::where('email', $email)->value('id');

        $match = $userId
            ? ['rehearsal_id' => $rehearsal->id, 'user_id' => $userId]
            : ['rehearsal_id' => $rehearsal->id, 'email' => $email];

        $existing = RehearsalSub::withTrashed()->where($match)->first();

        if ($existing && !$existing->trashed()) {
            throw ValidationException::withMessages([
                'sub' => "{$existing->name} is already invited to this rehearsal.",
            ]);
        }

        $attributes = [
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
            'band_role_id' => $roleId,
            'user_id'      => $userId,
            'name'         => $name,
            'email'        => $email,
            'phone'        => $phone,
            'invited_by'   => $actor->id,
        ];

        if ($existing) {
            $existing->restore();
            $existing->fill($attributes)->save();
            $sub = $existing;
        } else {
            $sub = RehearsalSub::create($attributes);
        }

        // Registered subs join the band's sub bench (same side effect as
        // SubInvitationService::inviteSubToEvent()).
        if ($userId) {
            BandSubs::firstOrCreate(['user_id' => $userId, 'band_id' => $band->id]);

            $user = User::find($userId);
            if ($user) {
                // Spatie runs with teams enabled and no mobile middleware sets
                // the team context, so scope the role to this band explicitly —
                // otherwise model_has_roles.team_id is null and the insert
                // fails. Mirrors the setPermissionsTeamId() bracketing used in
                // User::canWrite() and the web controllers.
                setPermissionsTeamId($band->id);
                if (!$user->hasRole('sub')) {
                    $user->assignRole('sub');
                }
                setPermissionsTeamId(0);
            }
        }

        ProcessRehearsalSubAdded::dispatch(
            $sub,
            $actor->id,
            sprintf('rehearsal-sub:%d:added:%s', $sub->id, now()->getPreciseTimestamp(3)),
        );

        return $sub;
    }

    /**
     * Remove a sub from a rehearsal (soft delete) and notify them.
     * 404s when the sub does not belong to this rehearsal.
     */
    public function remove(Rehearsal $rehearsal, int $subId, User $actor): void
    {
        $sub = $rehearsal->subs()->findOrFail($subId);

        $sub->delete();

        ProcessRehearsalSubRemoved::dispatch(
            $sub,
            $actor->id,
            sprintf('rehearsal-sub:%d:removed:%s', $sub->id, now()->getPreciseTimestamp(3)),
        );
    }
}
