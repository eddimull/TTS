<?php

namespace App\Services;

use App\Models\EventMember;
use App\Models\Events;
use App\Models\Roster;
use App\Models\RosterMember;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the future events that use a roster in sync with the roster's
 * current membership.
 *
 * "Future events using this roster" always means:
 *   events.roster_id = $roster->id AND events.date >= now()
 *
 * Payouts are intentionally not touched here: EventMember payouts are
 * computed on-demand from the current member list (see BandPayoutConfig),
 * so adding/removing members updates payouts automatically next view.
 */
class RosterReconcileService
{
    /**
     * Add a roster member to every future event using their roster that
     * doesn't already have them.
     *
     * Returns the number of events the member was added to.
     */
    public function addMemberToFutureEvents(RosterMember $member): int
    {
        $events = $this->futureEventsForRoster($member->roster_id);

        if ($events->isEmpty()) {
            return 0;
        }

        $added = 0;

        DB::transaction(function () use ($member, $events, &$added) {
            foreach ($events as $event) {
                if ($this->createEventMemberFor($member, $event)) {
                    $added++;
                }
            }
        });

        return $added;
    }

    /**
     * Remove a roster member from every future event using their roster.
     *
     * Force-deletes the EventMember rows regardless of attendance status or
     * payout, per product decision. Returns the number of events affected.
     */
    public function removeMemberFromFutureEvents(RosterMember $member): int
    {
        $eventIds = $this->futureEventsForRoster($member->roster_id)->pluck('id');

        if ($eventIds->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($member, $eventIds) {
            return $this->matchMemberOnEvents($member, $eventIds)
                ->withTrashed()
                ->forceDelete();
        });
    }

    /**
     * Compare a roster's current active membership against the members on its
     * future events.
     *
     * Returns:
     *   [
     *     'extra'   => [ ['event_member_id'=>.., 'display_name'=>.., 'event_count'=>..], ... ],
     *     'missing' => [ ['roster_member_id'=>.., 'display_name'=>.., 'event_count'=>..], ... ],
     *   ]
     *
     * - extra:   people on future events who are no longer active roster members
     *            (removable). Grouped per roster_member_id, falling back to
     *            user_id for legacy rows without a roster_member_id link.
     * - missing: active roster members absent from one or more future events
     *            (addable).
     */
    public function diffFutureEvents(Roster $roster): array
    {
        $events = $this->futureEventsForRoster($roster->id);
        $eventIds = $events->pluck('id');

        $activeMembers = $roster->members()
            ->where('is_active', true)
            ->with(['user', 'bandRole'])
            ->get();

        $activeRosterMemberIds = $activeMembers->pluck('id')->all();
        $activeUserIds = $activeMembers->pluck('user_id')->filter()->all();

        $eventMembers = EventMember::whereIn('event_id', $eventIds)
            ->with(['rosterMember', 'user'])
            ->get();

        // ---- extra: on events but not an active roster member -------------
        $extra = $eventMembers
            ->reject(function (EventMember $em) use ($activeRosterMemberIds, $activeUserIds) {
                if ($em->roster_member_id) {
                    return in_array($em->roster_member_id, $activeRosterMemberIds, true);
                }

                return $em->user_id && in_array($em->user_id, $activeUserIds, true);
            })
            ->groupBy(fn (EventMember $em) => $em->roster_member_id
                ? 'rm:' . $em->roster_member_id
                : 'user:' . ($em->user_id ?? 'unknown:' . $em->id))
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'roster_member_id' => $first->roster_member_id,
                    'user_id' => $first->user_id,
                    'display_name' => $first->display_name,
                    'event_count' => $rows->count(),
                ];
            })
            ->values()
            ->all();

        // ---- missing: active roster member absent from >=1 future event ---
        $missing = $activeMembers
            ->map(function (RosterMember $member) use ($eventIds, $eventMembers) {
                $presentEventIds = $eventMembers
                    ->filter(fn (EventMember $em) => $this->eventMemberMatchesRosterMember($em, $member))
                    ->pluck('event_id')
                    ->unique();

                $absentCount = $eventIds->diff($presentEventIds)->count();

                return [
                    'roster_member_id' => $member->id,
                    'user_id' => $member->user_id,
                    'display_name' => $member->display_name,
                    'event_count' => $absentCount,
                ];
            })
            ->filter(fn ($row) => $row['event_count'] > 0)
            ->values()
            ->all();

        return [
            'extra' => $extra,
            'missing' => $missing,
        ];
    }

    /**
     * Apply a selected set of reconcile actions.
     *
     * @param  array  $removeMemberIds  roster_member ids to remove from future events
     * @param  array  $addMemberIds     roster_member ids to add to future events
     * @return array{removed:int, added:int}
     */
    public function applyReconcile(Roster $roster, array $removeMemberIds, array $addMemberIds): array
    {
        $removed = 0;
        $added = 0;

        $removeMembers = RosterMember::whereIn('id', $removeMemberIds)
            ->where('roster_id', $roster->id)
            ->get();

        $addMembers = RosterMember::whereIn('id', $addMemberIds)
            ->where('roster_id', $roster->id)
            ->where('is_active', true)
            ->get();

        DB::transaction(function () use ($removeMembers, $addMembers, &$removed, &$added) {
            foreach ($removeMembers as $member) {
                $removed += $this->removeMemberFromFutureEvents($member);
            }

            foreach ($addMembers as $member) {
                $added += $this->addMemberToFutureEvents($member);
            }
        });

        return [
            'removed' => $removed,
            'added' => $added,
        ];
    }

    /**
     * Future events using the given roster.
     */
    private function futureEventsForRoster(?int $rosterId)
    {
        if (!$rosterId) {
            return collect();
        }

        return Events::where('roster_id', $rosterId)
            ->where('date', '>=', now())
            ->get();
    }

    /**
     * Create an EventMember for $member on $event, mirroring the field mapping
     * in Events::syncRosterMembers(). Restores a soft-deleted row if one exists
     * to avoid the (event_id, user_id) unique-constraint violation. Returns
     * true if a row was created/restored, false if an active row already
     * existed.
     */
    private function createEventMemberFor(RosterMember $member, Events $event): bool
    {
        $bandId = $event->eventable->band_id ?? null;

        if (!$bandId) {
            return false;
        }

        // Already present and active?
        $active = $this->matchMemberOnEvents($member, collect([$event->id]))->exists();
        if ($active) {
            return false;
        }

        $data = [
            'band_id' => $bandId,
            'roster_member_id' => $member->id,
            'slot_id' => $member->slot_id,
            'user_id' => $member->user_id,
            'band_role_id' => $member->band_role_id,
            'attendance_status' => 'confirmed',
        ];

        // Restore a soft-deleted row for the same person rather than insert,
        // so the unique (event_id, user_id) index is not violated.
        $trashed = EventMember::withTrashed()
            ->where('event_id', $event->id)
            ->where(function ($q) use ($member) {
                $q->where('roster_member_id', $member->id);
                if ($member->user_id) {
                    $q->orWhere('user_id', $member->user_id);
                }
            })
            ->whereNotNull('deleted_at')
            ->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update($data);

            return true;
        }

        EventMember::create(['event_id' => $event->id] + $data);

        return true;
    }

    /**
     * Query for the EventMember rows on the given events that represent the
     * given roster member (by roster_member_id, falling back to user_id).
     */
    private function matchMemberOnEvents(RosterMember $member, $eventIds)
    {
        return EventMember::whereIn('event_id', $eventIds)
            ->where(function ($q) use ($member) {
                $q->where('roster_member_id', $member->id);
                if ($member->user_id) {
                    $q->orWhere('user_id', $member->user_id);
                }
            });
    }

    /**
     * Whether a given EventMember row represents the given roster member.
     */
    private function eventMemberMatchesRosterMember(EventMember $em, RosterMember $member): bool
    {
        if ($em->roster_member_id) {
            return $em->roster_member_id === $member->id;
        }

        return $member->user_id && $em->user_id === $member->user_id;
    }
}
