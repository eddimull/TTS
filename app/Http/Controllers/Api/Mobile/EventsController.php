<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\BandEvents;
use App\Models\Bookings;
use App\Models\EventAttachment;
use App\Models\Events;
use App\Models\EventMember;
use App\Models\LiveSetlistSession;
use App\Models\RosterMember;
use App\Models\SubstituteCallList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventsController extends Controller
{
    /**
     * GET /api/mobile/bands/{band}/events
     */
    public function index(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');

        if (!$request->user()->canRead('events', $band->id)) {
            abort(403, 'You do not have permission to read events for this band.');
        }

        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d',
        ]);

        $query = Events::where(function ($q) use ($band) {
            $q->where(function ($inner) use ($band) {
                $inner->where('eventable_type', Bookings::class)
                    ->whereHas('eventable', fn($bq) => $bq->where('band_id', $band->id));
            })->orWhere(function ($inner) use ($band) {
                $inner->where('eventable_type', BandEvents::class)
                    ->whereHas('eventable', fn($bq) => $bq->where('band_id', $band->id));
            })->orWhere(function ($inner) use ($band) {
                $inner->where('eventable_type', 'App\\Models\\Rehearsal')
                    ->whereHas('eventable', fn($rq) => $rq->where('band_id', $band->id));
            });
        })->with(['eventable', 'type']);

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        $events = $query->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get();

        $eventIds = $events->pluck('id')->all();
        $liveSessions = collect();
        $membersByEvent = collect();
        if (!empty($eventIds)) {
            $liveSessions = LiveSetlistSession::whereIn('event_id', $eventIds)
                ->whereIn('status', ['active', 'paused'])
                ->get()
                ->keyBy('event_id');

            $membersByEvent = EventMember::whereIn('event_id', $eventIds)
                ->whereNull('deleted_at')
                ->get(['event_id', 'user_id', 'roster_member_id', 'name'])
                ->groupBy('event_id');
        }

        $mapped = $events->map(function ($event) use ($liveSessions, $membersByEvent) {
            $date = is_string($event->date) ? $event->date : $event->date->format('Y-m-d');
            $time = $event->time
                ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
                : null;

            $eventSource = match ($event->eventable_type) {
                Bookings::class, 'App\\Models\\Bookings' => 'booking',
                BandEvents::class, 'App\\Models\\BandEvents' => 'band_event',
                'App\\Models\\Rehearsal' => 'rehearsal',
                default => 'unknown',
            };

            // Compute roster status for this event
            $eventMembers = $membersByEvent->get($event->id, collect());
            $totalSlots = $eventMembers->count();
            $unfilledSlots = $eventMembers->filter(fn($m) => $m->user_id === null && $m->roster_member_id === null && empty($m->name))->count();
            $subsCount = $eventMembers->filter(fn($m) => $m->roster_member_id === null && ($m->user_id !== null || !empty($m->name)))->count();

            $rosterStatus = match(true) {
                $totalSlots === 0  => 'none',
                $unfilledSlots > 0 => 'red',
                $subsCount > 0     => 'yellow',
                default            => 'green',
            };

            return [
                'id'            => $event->id,
                'key'           => $event->key,
                'title'         => $event->title,
                'date'          => $date,
                'time'          => $time,
                'event_type'    => $event->type?->name,
                'event_source'  => $eventSource,
                'venue_name'    => $event->eventable?->venue_name ?? null,
                'venue_address' => $event->eventable?->venue_address ?? null,
                'status'        => $event->eventable?->status ?? null,
                'roster_status' => $rosterStatus,
                'live_session_id' => $liveSessions->has($event->id)
                    ? $liveSessions[$event->id]->id
                    : null,
            ];
        });

        return response()->json(['events' => $mapped->values()]);
    }

    /**
     * GET /api/mobile/events/{key}
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)
            ->with([
                'eventable.band',
                'eventable.contacts',
                'type',
                'eventMembers.user',
                'eventMembers.rosterMember',
                'eventMembers.bandRole',
                'eventMembers.slot',
                'attachments',
            ])
            ->firstOrFail();

        $band = $event->eventable?->band ?? null;

        if (!$band) {
            abort(404, 'Band not found for this event.');
        }

        if (!$request->user()->canRead('events', $band->id)) {
            abort(403, 'You do not have permission to read this event.');
        }

        $date = is_string($event->date) ? $event->date : $event->date->format('Y-m-d');
        $time = $event->time
            ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
            : null;

        $canWrite = $request->user()->canWrite('events', $band->id);

        $liveSessionId = LiveSetlistSession::where('event_id', $event->id)
            ->whereIn('status', ['active', 'paused'])
            ->value('id');

        $members = $event->eventMembers
            ->whereNull('deleted_at')
            ->map(function ($member) {
                // A slot is filled if it has a linked user, a roster member, or a manually-entered name.
                $isFilled = $member->user_id !== null
                    || $member->roster_member_id !== null
                    || !empty($member->name);

                // A filled slot is a sub when it has no roster_member_id — i.e. the person
                // filling it was not originally part of the synced roster.
                $isSub = $isFilled && $member->roster_member_id === null;

                return [
                    'id'                => $member->id,
                    'user_id'           => $member->user_id,
                    'roster_member_id'  => $member->roster_member_id,
                    'slot_id'           => $member->slot_id,
                    'band_role_id'      => $member->band_role_id,
                    'name'              => $member->displayName,
                    'attendance_status' => $member->attendance_status,
                    'role'              => $member->roleName,        // section name (BandRole)
                    'slot_name'         => $member->slot?->name,     // instrument name (RosterSlot)
                    'section_name'      => $member->bandRole?->name, // same as role, explicit alias
                    'is_filled'         => $isFilled,
                    'is_sub'            => $isSub,
                ];
            });

        // Supplement with synthetic entries for roster slots that have no EventMember row.
        // This happens when a slot exists on the roster template but the assigned member
        // was inactive at sync time (or was never assigned to this slot).
        if ($event->roster_id) {
            $event->loadMissing('roster.slots.bandRole');
            $coveredSlotIds = $members->pluck('slot_id')->filter()->all();

            foreach ($event->roster->slots as $slot) {
                if (in_array($slot->id, $coveredSlotIds)) {
                    continue;
                }
                $members->push([
                    'id'                => null, // synthetic — no EventMember row yet
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
        }

        $members = $members->values();

        // Compute roster status
        $totalSlots = $members->count();
        $unfilledSlots = $members->filter(fn($m) => !$m['is_filled'])->count();
        $subsCount = $members->filter(fn($m) => $m['is_sub'])->count();

        $rosterStatus = match(true) {
            $totalSlots === 0      => 'none',
            $unfilledSlots > 0     => 'red',
            $subsCount > 0         => 'yellow',
            default                => 'green',
        };

        // ── additional_data extraction ────────────────────────────────────────

        $ad = $event->additional_data; // cast to object or null

        // Timeline: array of {title, time} sorted chronologically
        $timeline = [];
        if ($ad && isset($ad->times) && is_array($ad->times)) {
            foreach ($ad->times as $t) {
                $timeline[] = [
                    'title' => $t->title ?? '',
                    'time'  => $t->time  ?? null,
                ];
            }
            usort($timeline, fn($a, $b) => strtotime($a['time'] ?? '0') - strtotime($b['time'] ?? '0'));
        }

        // Scalar flags
        $isPublic          = isset($ad->public)             ? (bool) $ad->public             : null;
        $outside           = isset($ad->outside)            ? (bool) $ad->outside            : null;
        $backlineProvided  = isset($ad->backline_provided)  ? (bool) $ad->backline_provided  : null;
        $productionNeeded  = isset($ad->production_needed)  ? (bool) $ad->production_needed  : null;

        // Attire (strip HTML)
        $attire = null;
        if ($ad && !empty($ad->attire)) {
            $attire = trim(strip_tags($ad->attire));
        }

        // Lodging
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

        // Performance
        $performance = null;
        if ($ad && isset($ad->performance)) {
            $p = $ad->performance;
            $songs = [];
            if (isset($p->songs) && is_array($p->songs)) {
                foreach ($p->songs as $song) {
                    $songs[] = [
                        'title' => $song->title ?? null,
                        'url'   => $song->url   ?? null,
                    ];
                }
            }
            $charts = [];
            if (isset($p->charts) && is_array($p->charts)) {
                foreach ($p->charts as $chart) {
                    $charts[] = [
                        'title'    => $chart->title    ?? '',
                        'composer' => $chart->composer ?? null,
                    ];
                }
            }
            $performance = [
                'notes'  => isset($p->notes) ? trim(strip_tags($p->notes)) : null,
                'songs'  => $songs,
                'charts' => $charts,
            ];
        }

        // Wedding
        $wedding = null;
        if ($ad && isset($ad->wedding)) {
            $w = $ad->wedding;
            $dances = [];
            if (isset($w->dances) && is_array($w->dances)) {
                foreach ($w->dances as $dance) {
                    $dances[] = [
                        'title' => $dance->title ?? '',
                        'data'  => $dance->data  ?? null,
                    ];
                }
            }
            $wedding = [
                'onsite' => isset($w->onsite) ? (bool) $w->onsite : null,
                'dances' => $dances,
            ];
        }

        // Contacts (from booking)
        $contacts = [];
        $isBooking = in_array($event->eventable_type, [Bookings::class, 'App\\Models\\Bookings']);
        if ($isBooking && $event->eventable && $event->eventable->relationLoaded('contacts')) {
            $contacts = $event->eventable->contacts->map(function ($contact) {
                return [
                    'id'    => $contact->id,
                    'name'  => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'role'  => $contact->pivot->role ?? null,
                ];
            })->values()->toArray();
        }

        // Attachments
        $attachments = $event->attachments->map(function ($a) {
            return [
                'id'        => $a->id,
                'filename'  => $a->filename,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'url'       => $a->url,
            ];
        })->values()->toArray();

        return response()->json([
            'event' => [
                'id'                => $event->id,
                'key'               => $event->key,
                'title'             => $event->title,
                'date'              => $date,
                'time'              => $time,
                'notes'             => $event->notes,
                'event_type'        => $event->type?->name,
                'event_type_id'     => $event->event_type_id,
                'venue_name'        => $event->eventable?->venue_name ?? null,
                'venue_address'     => $event->eventable?->venue_address ?? null,
                'status'            => $event->eventable?->status ?? null,
                'eventable_type'    => class_basename($event->eventable_type),
                'eventable_id'      => $event->eventable_id,
                'can_write'         => $canWrite,
                'live_session_id'   => $liveSessionId,
                'roster_status'     => $rosterStatus,
                'members'           => $members,
                // additional_data fields
                'timeline'          => $timeline,
                'is_public'         => $isPublic,
                'attire'            => $attire,
                'outside'           => $outside,
                'backline_provided' => $backlineProvided,
                'production_needed' => $productionNeeded,
                'lodging'           => $lodging,
                'performance'       => $performance,
                'wedding'           => $wedding,
                'contacts'          => $contacts,
                'attachments'       => $attachments,
            ],
        ]);
    }

    /**
     * GET /api/mobile/events/{key}/subs?band_role_id={id}
     * Returns the substitute call list for a given role, ordered by priority.
     */
    public function subs(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)
            ->with('eventable.band')
            ->firstOrFail();

        $band = $event->eventable?->band ?? null;
        if (!$band) {
            abort(404, 'Band not found.');
        }
        if (!$request->user()->canRead('events', $band->id)) {
            abort(403, 'You do not have permission to view subs for this event.');
        }

        $request->validate(['band_role_id' => 'required|integer']);
        $bandRoleId = (int) $request->input('band_role_id');

        $subs = SubstituteCallList::where('band_id', $band->id)
            ->where('band_role_id', $bandRoleId)
            ->with(['rosterMember.user', 'bandRole'])
            ->orderBy('priority')
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'name'             => $s->displayName,
                'email'            => $s->displayEmail,
                'band_role_id'     => $s->band_role_id,
                'role_name'        => $s->bandRole?->name,
                'roster_member_id' => $s->roster_member_id,
                'is_custom'        => $s->isCustomPlayer(),
                'priority'         => $s->priority,
            ]);

        return response()->json(['subs' => $subs->values()]);
    }

    /**
     * POST /api/mobile/events/{key}/members/{memberId}/sub
     * Assigns a substitute to an event member slot.
     *
     * When memberId is 0 the slot has no EventMember row yet (synthetic unfilled slot).
     * In that case, supply slot_id in the body and the row will be created.
     *
     * Body options:
     *   { roster_member_id: int }               — assign from roster call list
     *   { name: string, email?: string }         — assign a custom/non-roster player
     *   { clear: true }                          — revert existing slot to unfilled
     *   + slot_id: int (required when memberId=0)
     */
    public function assignSub(Request $request, string $key, int $memberId): JsonResponse
    {
        $event = Events::where('key', $key)
            ->with('eventable.band')
            ->firstOrFail();

        $band = $event->eventable?->band ?? null;
        if (!$band) {
            abort(404, 'Band not found.');
        }
        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403, 'You do not have permission to edit this event.');
        }

        $request->validate([
            'clear'            => 'sometimes|boolean',
            'slot_id'          => 'sometimes|integer|exists:roster_slots,id',
            'roster_member_id' => 'sometimes|integer|exists:roster_members,id',
            'name'             => 'sometimes|string|max:255',
            'email'            => 'sometimes|nullable|email|max:255',
        ]);

        if ($memberId === 0) {
            // Synthetic slot — no EventMember row exists yet. Create one.
            $slotId = $request->input('slot_id');
            if (!$slotId) {
                abort(422, 'slot_id is required when creating a new slot assignment.');
            }

            $slot = \App\Models\RosterSlot::findOrFail($slotId);

            $memberData = [
                'event_id'          => $event->id,
                'band_id'           => $band->id,
                'slot_id'           => $slot->id,
                'band_role_id'      => $slot->band_role_id,
                'attendance_status' => 'confirmed',
            ];

            if ($request->filled('roster_member_id')) {
                $rosterMember = RosterMember::findOrFail($request->input('roster_member_id'));
                $memberData['roster_member_id'] = $rosterMember->id;
                $memberData['user_id']          = $rosterMember->user_id;
                $memberData['name']             = $rosterMember->displayName;
                $memberData['email']            = $rosterMember->displayEmail;
            } elseif ($request->filled('name')) {
                $memberData['name']  = $request->input('name');
                $memberData['email'] = $request->input('email');
            } else {
                abort(422, 'Provide roster_member_id or name for new slot assignment.');
            }

            $member = EventMember::create($memberData);
            return response()->json(['message' => 'Sub assigned.', 'member_id' => $member->id]);
        }

        // Existing EventMember row — update it.
        $member = EventMember::where('id', $memberId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        if ($request->boolean('clear')) {
            $member->update([
                'user_id'           => null,
                'name'              => null,
                'email'             => null,
                'roster_member_id'  => null,
                'attendance_status' => null,
            ]);
        } elseif ($request->filled('roster_member_id')) {
            $rosterMember = RosterMember::findOrFail($request->input('roster_member_id'));
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

        return response()->json(['message' => 'Sub assigned.', 'member_id' => $member->id]);
    }

    /**
     * PATCH /api/mobile/events/{key}
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)
            ->with('eventable.band')
            ->firstOrFail();

        $band = $event->eventable?->band ?? null;

        if (!$band) {
            abort(404, 'Band not found for this event.');
        }

        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403, 'You do not have permission to edit this event.');
        }

        $request->validate([
            'title'                   => 'sometimes|string|max:255',
            'date'                    => 'sometimes|date_format:Y-m-d',
            'time'                    => 'sometimes|nullable|date_format:H:i',
            'notes'                   => 'sometimes|nullable|string',
            'venue_name'              => 'sometimes|nullable|string|max:255',
            'venue_address'           => 'sometimes|nullable|string|max:255',
            'attire'                  => 'sometimes|nullable|string|max:255',
            'is_public'               => 'sometimes|boolean',
            'outside'                 => 'sometimes|boolean',
            'backline_provided'       => 'sometimes|boolean',
            'production_needed'       => 'sometimes|boolean',
            'timeline'                => 'sometimes|array',
            'timeline.*.title'        => 'required_with:timeline|string|max:255',
            'timeline.*.time'         => 'nullable|string|max:20',
            'wedding'                 => 'sometimes|array',
            'wedding.onsite'          => 'sometimes|nullable|boolean',
            'wedding.dances'          => 'sometimes|array',
            'wedding.dances.*.title'  => 'required_with:wedding.dances|string|max:100',
            'wedding.dances.*.data'   => 'nullable|string|max:255',
        ]);

        // Update core event fields
        $eventData = $request->only(['title', 'date', 'time', 'notes']);

        // Merge additional_data fields without clobbering existing keys
        $ad = $event->additional_data ?? new \stdClass();

        if ($request->has('attire')) {
            $ad->attire = $request->input('attire');
        }
        if ($request->has('is_public')) {
            $ad->public = $request->boolean('is_public');
        }
        if ($request->has('outside')) {
            $ad->outside = $request->boolean('outside');
        }
        if ($request->has('backline_provided')) {
            $ad->backline_provided = $request->boolean('backline_provided');
        }
        if ($request->has('production_needed')) {
            $ad->production_needed = $request->boolean('production_needed');
        }

        if ($request->has('timeline')) {
            $times = [];
            foreach ($request->input('timeline', []) as $entry) {
                $times[] = [
                    'title' => $entry['title'],
                    'time'  => $entry['time'] ?? null,
                ];
            }
            $ad->times = $times;
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
                $dances = [];
                foreach ($w['dances'] as $dance) {
                    $dances[] = [
                        'title' => $dance['title'],
                        'data'  => $dance['data'] ?? null,
                    ];
                }
                $ad->wedding->dances = $dances;
            }
        }

        $eventData['additional_data'] = $ad;
        $event->update($eventData);

        // Update eventable venue fields
        $venueData = array_filter(
            $request->only(['venue_name', 'venue_address']),
            fn($v) => $v !== null
        );

        if (!empty($venueData) && $event->eventable) {
            $event->eventable->update($venueData);
        }

        return response()->json(['message' => 'Event updated successfully.']);
    }

    /**
     * POST /api/mobile/events/{key}/attachments
     */
    public function uploadAttachment(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)
            ->with('eventable.band')
            ->firstOrFail();

        $band = $event->eventable?->band ?? null;

        if (!$band) {
            abort(404, 'Band not found for this event.');
        }

        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403, 'You do not have permission to edit this event.');
        }

        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file        = $request->file('file');
        $storagePath = $band->site_name . '/event_uploads';
        $disk        = config('filesystems.default');
        $extension   = $file->getClientOriginalExtension();
        $filename    = Str::uuid() . ($extension ? '.' . $extension : '');
        $path        = $file->storeAs($storagePath, $filename, $disk);

        $attachment = EventAttachment::create([
            'event_id'        => $event->id,
            'filename'        => $file->getClientOriginalName(),
            'stored_filename' => $path,
            'mime_type'       => $file->getMimeType(),
            'file_size'       => $file->getSize(),
            'disk'            => $disk,
        ]);

        return response()->json([
            'attachment' => [
                'id'        => $attachment->id,
                'filename'  => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'url'       => $attachment->url,
            ],
        ], 201);
    }

    /**
     * DELETE /api/mobile/events/{key}/attachments/{attachmentId}
     */
    public function deleteAttachment(Request $request, string $key, int $attachmentId): JsonResponse
    {
        $event = Events::where('key', $key)
            ->with('eventable.band')
            ->firstOrFail();

        $band = $event->eventable?->band ?? null;

        if (!$band) {
            abort(404, 'Band not found for this event.');
        }

        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403, 'You do not have permission to edit this event.');
        }

        $attachment = EventAttachment::where('id', $attachmentId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
