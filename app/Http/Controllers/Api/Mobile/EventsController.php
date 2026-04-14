<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\AssignSubRequest;
use App\Http\Requests\Mobile\EventIndexRequest;
use App\Http\Requests\Mobile\EventSubsRequest;
use App\Http\Requests\Mobile\UpdateEventRequest;
use App\Http\Requests\Mobile\UploadEventAttachmentRequest;
use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventAttachment;
use App\Models\Events;
use App\Models\EventMember;
use App\Models\LiveSetlistSession;
use App\Models\SubstituteCallList;
use App\Services\Mobile\EventDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventsController extends Controller
{
    public function __construct(private readonly EventDataService $eventData) {}

    /**
     * GET /api/mobile/bands/{band}/events
     */
    public function index(EventIndexRequest $request, Bands $band): JsonResponse
    {
        if (!$request->user()->canRead('events', $band->id)) {
            abort(403);
        }

        $events = Events::where(function ($q) use ($band) {
            $q->where(function ($inner) use ($band) {
                $inner->where('eventable_type', Bookings::class)
                    ->whereHas('eventable', fn ($bq) => $bq->where('band_id', $band->id));
            })->orWhere(function ($inner) use ($band) {
                $inner->where('eventable_type', BandEvents::class)
                    ->whereHas('eventable', fn ($bq) => $bq->where('band_id', $band->id));
            })->orWhere(function ($inner) use ($band) {
                $inner->where('eventable_type', 'App\\Models\\Rehearsal')
                    ->whereHas('eventable', fn ($rq) => $rq->where('band_id', $band->id));
            });
        })
            ->with(['eventable', 'type'])
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->input('from')))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('date', '<=', $request->input('to')))
            ->orderBy('date')->orderBy('time')
            ->get();

        $eventIds = $events->pluck('id')->all();

        $liveSessions   = collect();
        $membersByEvent = collect();

        if (!empty($eventIds)) {
            $liveSessions = LiveSetlistSession::whereIn('event_id', $eventIds)
                ->whereIn('status', ['active', 'paused'])
                ->get()->keyBy('event_id');

            $membersByEvent = EventMember::whereIn('event_id', $eventIds)
                ->whereNull('deleted_at')
                ->get(['event_id', 'user_id', 'roster_member_id', 'name'])
                ->groupBy('event_id');
        }

        $mapped = $events->map(fn ($event) => $this->eventData->formatForList(
            $event,
            $membersByEvent->get($event->id, collect()),
            $liveSessions->get($event->id),
        ));

        return response()->json(['events' => $mapped->values()]);
    }

    /**
     * GET /api/mobile/events/{event}
     */
    public function show(Request $request, Events $event): JsonResponse
    {
        $event->load([
            'eventable.band', 'eventable.contacts',
            'type', 'eventMembers.user', 'eventMembers.rosterMember',
            'eventMembers.bandRole', 'eventMembers.slot', 'attachments',
        ]);

        $band = $event->eventable?->band ?? abort(404, 'Band not found for this event.');

        if (!$request->user()->canRead('events', $band->id)) {
            abort(403);
        }

        $liveSessionId = LiveSetlistSession::where('event_id', $event->id)
            ->whereIn('status', ['active', 'paused'])
            ->value('id');

        return response()->json([
            'event' => $this->eventData->formatForShow(
                $event,
                $request->user()->canWrite('events', $band->id),
                $liveSessionId,
            ),
        ]);
    }

    /**
     * GET /api/mobile/events/{event}/subs?band_role_id={id}
     */
    public function subs(EventSubsRequest $request, Events $event): JsonResponse
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable?->band ?? abort(404, 'Band not found.');

        if (!$request->user()->canRead('events', $band->id)) {
            abort(403);
        }

        $subs = SubstituteCallList::where('band_id', $band->id)
            ->where('band_role_id', (int) $request->input('band_role_id'))
            ->with(['rosterMember.user', 'bandRole'])
            ->orderBy('priority')
            ->get()
            ->map(fn ($s) => [
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
     * POST /api/mobile/events/{event}/members/{memberId}/sub
     */
    public function assignSub(AssignSubRequest $request, Events $event, int $memberId): JsonResponse
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable?->band ?? abort(404, 'Band not found.');

        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403);
        }

        if ($memberId === 0) {
            $member = $this->eventData->createSlotAssignment($request, $event, $band);
        } else {
            $member = EventMember::where('event_id', $event->id)->findOrFail($memberId);
            $this->eventData->updateSlotAssignment($request, $member);
        }

        return response()->json(['message' => 'Sub assigned.', 'member_id' => $member->id]);
    }

    /**
     * PATCH /api/mobile/events/{event}
     */
    public function update(UpdateEventRequest $request, Events $event): JsonResponse
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable?->band ?? abort(404, 'Band not found for this event.');

        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $ad = $this->eventData->applyAdditionalDataChanges(
            $event->additional_data ?? new \stdClass(),
            $request,
        );

        $event->update([
            ...$request->only(['title', 'date', 'time', 'notes']),
            'additional_data' => $ad,
        ]);

        $venueData = array_filter($request->only(['venue_name', 'venue_address']), fn ($v) => $v !== null);
        if (!empty($venueData) && $event->eventable) {
            $event->eventable->update($venueData);
        }

        return response()->json(['message' => 'Event updated successfully.']);
    }

    /**
     * POST /api/mobile/events/{event}/attachments
     */
    public function uploadAttachment(UploadEventAttachmentRequest $request, Events $event): JsonResponse
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable?->band ?? abort(404, 'Band not found for this event.');

        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $file      = $request->file('file');
        $disk      = config('filesystems.default');
        $extension = $file->getClientOriginalExtension();
        $filename  = Str::uuid() . ($extension ? '.' . $extension : '');
        $path      = $file->storeAs($band->site_name . '/event_uploads', $filename, $disk);

        $attachment = EventAttachment::create([
            'event_id'        => $event->id,
            'filename'        => $file->getClientOriginalName(),
            'stored_filename' => $path,
            'mime_type'       => $file->getMimeType(),
            'file_size'       => $file->getSize(),
            'disk'            => $disk,
        ]);

        return response()->json(['attachment' => $this->eventData->formatAttachment($attachment)], 201);
    }

    /**
     * DELETE /api/mobile/events/{event}/attachments/{attachment}
     */
    public function deleteAttachment(Request $request, Events $event, EventAttachment $attachment): JsonResponse
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable?->band ?? abort(404, 'Band not found for this event.');

        if (!$request->user()->canWrite('events', $band->id)) {
            abort(403);
        }

        abort_if($attachment->event_id !== $event->id, 404);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
