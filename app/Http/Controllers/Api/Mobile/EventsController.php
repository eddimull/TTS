<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\BandEvents;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\LiveSetlistSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    /**
     * GET /api/mobile/bands/{band}/events
     *
     * List events for a band. The `mobile.band` middleware resolves the band from
     * the X-Band-ID header and stores it on the request as `mobile_band`.
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
                // Booking events
                $inner->where('eventable_type', Bookings::class)
                    ->whereHas('eventable', fn($bq) => $bq->where('band_id', $band->id));
            })->orWhere(function ($inner) use ($band) {
                // Legacy band events
                $inner->where('eventable_type', BandEvents::class)
                    ->whereHas('eventable', fn($bq) => $bq->where('band_id', $band->id));
            })->orWhere(function ($inner) use ($band) {
                // Rehearsal events
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

        // Collect event IDs to batch-check for live sessions
        $eventIds = $events->pluck('id')->all();
        $liveSessions = collect();
        if (!empty($eventIds)) {
            $liveSessions = LiveSetlistSession::whereIn('event_id', $eventIds)
                ->whereIn('status', ['active', 'paused'])
                ->get()
                ->keyBy('event_id');
        }

        $mapped = $events->map(function ($event) use ($liveSessions) {
            $date = is_string($event->date) ? $event->date : $event->date->format('Y-m-d');
            $time = $event->time
                ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
                : null;

            $eventableType = class_basename($event->eventable_type);
            $eventSource = match ($event->eventable_type) {
                Bookings::class, 'App\\Models\\Bookings' => 'booking',
                BandEvents::class, 'App\\Models\\BandEvents' => 'band_event',
                'App\\Models\\Rehearsal' => 'rehearsal',
                default => 'unknown',
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
                'live_session_id' => $liveSessions->has($event->id)
                    ? $liveSessions[$event->id]->id
                    : null,
            ];
        });

        return response()->json(['events' => $mapped->values()]);
    }

    /**
     * GET /api/mobile/events/{key}
     *
     * Return full detail for a single event looked up by its unique key.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)
            ->with(['eventable.band', 'type', 'eventMembers.user', 'eventMembers.rosterMember', 'eventMembers.bandRole'])
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

        // Check for an active/paused live setlist session
        $liveSessionId = LiveSetlistSession::where('event_id', $event->id)
            ->whereIn('status', ['active', 'paused'])
            ->value('id');

        // Build members list from event_members (exclude soft-deleted)
        $members = $event->eventMembers
            ->whereNull('deleted_at')
            ->map(function ($member) {
                $name = $member->displayName;
                $role = $member->roleName;

                return [
                    'id'                => $member->id,
                    'user_id'           => $member->user_id,
                    'name'              => $name,
                    'attendance_status' => $member->attendance_status,
                    'role'              => $role,
                ];
            })
            ->values();

        return response()->json([
            'event' => [
                'id'             => $event->id,
                'key'            => $event->key,
                'title'          => $event->title,
                'date'           => $date,
                'time'           => $time,
                'notes'          => $event->notes,
                'event_type'     => $event->type?->name,
                'event_type_id'  => $event->event_type_id,
                'venue_name'     => $event->eventable?->venue_name ?? null,
                'venue_address'  => $event->eventable?->venue_address ?? null,
                'status'         => $event->eventable?->status ?? null,
                'eventable_type' => class_basename($event->eventable_type),
                'eventable_id'   => $event->eventable_id,
                'can_write'      => $canWrite,
                'live_session_id' => $liveSessionId,
                'members'        => $members,
            ],
        ]);
    }
}
