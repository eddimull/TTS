<?php

namespace App\Services\Mobile;

use App\Models\BandEvents;
use App\Models\Bookings;
use App\Models\EventAttachment;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\RosterSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventDataService
{
    /**
     * Compute 'none' | 'red' | 'yellow' | 'green' roster status from a flat
     * collection of member arrays (each must have is_filled / is_sub keys).
     */
    public function rosterStatus(Collection $members): string
    {
        $total    = $members->count();
        $unfilled = $members->filter(fn ($m) => !($m['is_filled'] ?? false))->count();
        $subs     = $members->filter(fn ($m) => $m['is_sub'] ?? false)->count();

        return match (true) {
            $total === 0   => 'none',
            $unfilled > 0  => 'red',
            $subs > 0      => 'yellow',
            default        => 'green',
        };
    }

    /**
     * Same as rosterStatus() but works on raw EventMember model rows (from the
     * list query) where we only have user_id / roster_member_id / name columns.
     */
    public function rosterStatusFromRaw(Collection $eventMembers): string
    {
        $total    = $eventMembers->count();
        $unfilled = $eventMembers->filter(
            fn ($m) => $m->user_id === null && $m->roster_member_id === null && empty($m->name)
        )->count();
        $subs = $eventMembers->filter(
            fn ($m) => $m->roster_member_id === null && ($m->user_id !== null || !empty($m->name))
        )->count();

        return match (true) {
            $total === 0  => 'none',
            $unfilled > 0 => 'red',
            $subs > 0     => 'yellow',
            default       => 'green',
        };
    }

    /**
     * Map an EventMember model to the array shape used in the show() response.
     */
    public function formatMember(EventMember $member): array
    {
        $isFilled = $member->user_id !== null
            || $member->roster_member_id !== null
            || !empty($member->name);

        $isSub = $isFilled && $member->roster_member_id === null;

        return [
            'id'                => $member->id,
            'user_id'           => $member->user_id,
            'roster_member_id'  => $member->roster_member_id,
            'slot_id'           => $member->slot_id,
            'band_role_id'      => $member->band_role_id,
            'name'              => $member->displayName,
            'attendance_status' => $member->attendance_status,
            'role'              => $member->roleName,
            'slot_name'         => $member->slot?->name,
            'section_name'      => $member->bandRole?->name,
            'is_filled'         => $isFilled,
            'is_sub'            => $isSub,
        ];
    }

    /**
     * Append synthetic entries for roster slots that have no EventMember row.
     */
    public function appendSyntheticSlots(Events $event, Collection $members): Collection
    {
        if (!$event->roster_id) {
            return $members;
        }

        $event->loadMissing('roster.slots.bandRole');
        $coveredSlotIds = $members->pluck('slot_id')->filter()->all();

        foreach ($event->roster->slots as $slot) {
            if (in_array($slot->id, $coveredSlotIds)) {
                continue;
            }
            $members->push([
                'id'                => null,
                'user_id'           => null,
                'roster_member_id'  => null,
                'slot_id'           => $slot->id,
                'band_role_id'      => $slot->band_role_id,
                'name'              => null,
                'attendance_status' => null,
                'role'              => $slot->bandRole?->name,
                'slot_name'         => $slot->name,
                'section_name'      => $slot->bandRole?->name,
                'is_filled'         => false,
                'is_sub'            => false,
            ]);
        }

        return $members;
    }

    /**
     * Create a new EventMember record for an unoccupied slot.
     */
    public function createSlotAssignment(\Illuminate\Http\Request $request, Events $event, $band): EventMember
    {
        $slotId = $request->input('slot_id');
        if (!$slotId) {
            abort(422, 'slot_id is required when creating a new slot assignment.');
        }

        $slot = RosterSlot::findOrFail($slotId);

        $data = [
            'event_id'          => $event->id,
            'band_id'           => $band->id,
            'slot_id'           => $slot->id,
            'band_role_id'      => $slot->band_role_id,
            'attendance_status' => 'confirmed',
        ];

        if ($request->filled('roster_member_id')) {
            $rosterMember             = \App\Models\RosterMember::findOrFail($request->input('roster_member_id'));
            $data['roster_member_id'] = $rosterMember->id;
            $data['user_id']          = $rosterMember->user_id;
            $data['name']             = $rosterMember->displayName;
            $data['email']            = $rosterMember->displayEmail;
        } elseif ($request->filled('name')) {
            $data['name']  = $request->input('name');
            $data['email'] = $request->input('email');
        } else {
            abort(422, 'Provide roster_member_id or name for new slot assignment.');
        }

        return EventMember::create($data);
    }

    /**
     * Update an existing EventMember slot assignment (assign sub, custom player, or clear).
     */
    public function updateSlotAssignment(\Illuminate\Http\Request $request, EventMember $member): void
    {
        if ($request->boolean('clear')) {
            $member->update([
                'user_id'           => null,
                'name'              => null,
                'email'             => null,
                'roster_member_id'  => null,
                'attendance_status' => null,
            ]);
        } elseif ($request->filled('roster_member_id')) {
            $rosterMember = \App\Models\RosterMember::findOrFail($request->input('roster_member_id'));
            $member->update([
                'roster_member_id'  => $rosterMember->id,
                'user_id'           => $rosterMember->user_id,
                'name'              => $rosterMember->displayName,
                'email'             => $rosterMember->displayEmail,
                'attendance_status' => 'confirmed',
            ]);
        } elseif ($request->filled('name')) {
            $member->update([
                'roster_member_id'  => null,
                'user_id'           => null,
                'name'              => $request->input('name'),
                'email'             => $request->input('email'),
                'attendance_status' => 'confirmed',
            ]);
        } else {
            abort(422, 'Provide roster_member_id, name, or clear=true.');
        }
    }

    /**
     * Parse the additional_data JSON blob into discrete fields.
     *
     * Returns an array with keys: timeline, is_public, attire, outside,
     * backline_provided, production_needed, lodging, performance, wedding.
     */
    public function parseAdditionalData(mixed $ad): array
    {
        $timeline = [];
        if ($ad && isset($ad->times) && is_array($ad->times)) {
            foreach ($ad->times as $t) {
                $timeline[] = ['title' => $t->title ?? '', 'time' => $t->time ?? null];
            }
            usort($timeline, fn ($a, $b) => strtotime($a['time'] ?? '0') - strtotime($b['time'] ?? '0'));
        }

        $lodging = [];
        if ($ad && isset($ad->lodging) && is_array($ad->lodging)) {
            foreach ($ad->lodging as $item) {
                $lodging[] = [
                    'type'  => $item->type  ?? 'text',
                    'title' => $item->title ?? '',
                    'data'  => $item->data  ?? null,
                ];
            }
        }

        $performance = null;
        if ($ad && isset($ad->performance)) {
            $p     = $ad->performance;
            $songs = [];
            if (isset($p->songs) && is_array($p->songs)) {
                foreach ($p->songs as $song) {
                    $songs[] = ['title' => $song->title ?? null, 'url' => $song->url ?? null];
                }
            }
            $charts = [];
            if (isset($p->charts) && is_array($p->charts)) {
                foreach ($p->charts as $chart) {
                    $charts[] = ['title' => $chart->title ?? '', 'composer' => $chart->composer ?? null];
                }
            }
            $performance = [
                'notes'  => isset($p->notes) ? trim(strip_tags($p->notes)) : null,
                'songs'  => $songs,
                'charts' => $charts,
            ];
        }

        $wedding = null;
        if ($ad && isset($ad->wedding)) {
            $w      = $ad->wedding;
            $dances = [];
            if (isset($w->dances) && is_array($w->dances)) {
                foreach ($w->dances as $dance) {
                    $dances[] = ['title' => $dance->title ?? '', 'data' => $dance->data ?? null];
                }
            }
            $wedding = [
                'onsite' => isset($w->onsite) ? (bool) $w->onsite : null,
                'dances' => $dances,
            ];
        }

        return [
            'timeline'          => $timeline,
            'is_public'         => isset($ad->public)            ? (bool) $ad->public            : null,
            'attire'            => ($ad && !empty($ad->attire))  ? trim(strip_tags($ad->attire))  : null,
            'outside'           => isset($ad->outside)           ? (bool) $ad->outside            : null,
            'backline_provided' => isset($ad->backline_provided) ? (bool) $ad->backline_provided  : null,
            'production_needed' => isset($ad->production_needed) ? (bool) $ad->production_needed  : null,
            'lodging'           => $lodging,
            'performance'       => $performance,
            'wedding'           => $wedding,
        ];
    }

    /**
     * Format a single event for the index list response.
     */
    public function formatForList(Events $event, Collection $members, $liveSession): array
    {
        $date = is_string($event->date) ? $event->date : $event->date->format('Y-m-d');
        $time = $event->time
            ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
            : null;

        $eventSource = match ($event->eventable_type) {
            Bookings::class, 'App\\Models\\Bookings'     => 'booking',
            BandEvents::class, 'App\\Models\\BandEvents' => 'band_event',
            'App\\Models\\Rehearsal'                     => 'rehearsal',
            default                                      => 'unknown',
        };

        return [
            'id'              => $event->id,
            'key'             => $event->key,
            'title'           => $event->title,
            'date'            => $date,
            'time'            => $time,
            'event_type'      => $event->type?->name,
            'event_source'    => $eventSource,
            'venue_name'      => $event->eventable?->venue_name ?? null,
            'venue_address'   => $event->eventable?->venue_address ?? null,
            'status'          => $event->eventable?->status ?? null,
            'roster_status'   => $this->rosterStatusFromRaw($members),
            'live_session_id' => $liveSession?->id,
        ];
    }

    /**
     * Format a single event for the show/detail response.
     */
    public function formatForShow(Events $event, bool $canWrite, ?int $liveSessionId): array
    {
        $date = is_string($event->date) ? $event->date : $event->date->format('Y-m-d');
        $time = $event->time
            ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
            : null;

        $members = $event->eventMembers
            ->whereNull('deleted_at')
            ->map(fn ($member) => $this->formatMember($member));

        $members        = $this->appendSyntheticSlots($event, $members)->values();
        $rosterStatus   = $this->rosterStatus($members);
        $additionalData = $this->parseAdditionalData($event->additional_data);

        $isBooking = in_array($event->eventable_type, [Bookings::class, 'App\\Models\\Bookings']);
        $contacts  = [];
        if ($isBooking && $event->eventable && $event->eventable->relationLoaded('contacts')) {
            $contacts = $event->eventable->contacts->map(fn ($c) => [
                'id'    => $c->id,
                'name'  => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'role'  => $c->pivot->role ?? null,
            ])->values()->toArray();
        }

        $attachments = $event->attachments->map(fn ($a) => $this->formatAttachment($a))->values()->toArray();

        return [
            'id'              => $event->id,
            'key'             => $event->key,
            'title'           => $event->title,
            'date'            => $date,
            'time'            => $time,
            'notes'           => $event->notes,
            'event_type'      => $event->type?->name,
            'event_type_id'   => $event->event_type_id,
            'venue_name'      => $event->eventable?->venue_name ?? null,
            'venue_address'   => $event->eventable?->venue_address ?? null,
            'status'          => $event->eventable?->status ?? null,
            'eventable_type'  => class_basename($event->eventable_type),
            'eventable_id'    => $event->eventable_id,
            'can_write'       => $canWrite,
            'live_session_id' => $liveSessionId,
            'roster_status'   => $rosterStatus,
            'members'         => $members,
            'contacts'        => $contacts,
            'attachments'     => $attachments,
            ...$additionalData,
        ];
    }

    /**
     * Format an attachment model to its API shape.
     */
    public function formatAttachment(EventAttachment $attachment): array
    {
        return [
            'id'        => $attachment->id,
            'filename'  => $attachment->filename,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'url'       => $attachment->url,
        ];
    }

    /**
     * Apply incoming request fields to the event's additional_data blob.
     */
    public function applyAdditionalDataChanges(\stdClass $ad, Request $request): \stdClass
    {
        foreach (['attire', 'outside', 'backline_provided', 'production_needed'] as $field) {
            if ($request->has($field)) {
                $ad->$field = is_bool($request->input($field))
                    ? $request->boolean($field)
                    : $request->input($field);
            }
        }

        if ($request->has('is_public')) {
            $ad->public = $request->boolean('is_public');
        }

        if ($request->has('timeline')) {
            $ad->times = array_map(
                fn ($entry) => ['title' => $entry['title'], 'time' => $entry['time'] ?? null],
                $request->input('timeline', [])
            );
        }

        if ($request->has('wedding')) {
            if (!isset($ad->wedding) || !is_object($ad->wedding)) {
                $ad->wedding = new \stdClass();
            }
            $w = $request->input('wedding');
            if (array_key_exists('onsite', $w)) {
                $ad->wedding->onsite = isset($w['onsite']) ? (bool) $w['onsite'] : null;
            }
            if (array_key_exists('dances', $w)) {
                $ad->wedding->dances = array_map(
                    fn ($dance) => ['title' => $dance['title'], 'data' => $dance['data'] ?? null],
                    $w['dances']
                );
            }
        }

        return $ad;
    }
}
