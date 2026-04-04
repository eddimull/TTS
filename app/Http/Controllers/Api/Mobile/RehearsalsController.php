<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Rehearsal;
use App\Models\EventTypes;
use App\Models\RehearsalSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RehearsalsController extends Controller
{
    /**
     * GET /api/mobile/bands/{band}/rehearsal-schedules
     *
     * List all rehearsal schedules for a band with upcoming rehearsals (next 60 days).
     */
    public function schedules(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');

        if (!$request->user()->canRead('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to read rehearsals for this band.');
        }

        $cutoff = now()->addDays(60)->toDateString();

        $schedules = RehearsalSchedule::where('band_id', $band->id)
            ->with(['rehearsals' => function ($query) use ($cutoff) {
                $query->whereHas('events', function ($eq) use ($cutoff) {
                    $eq->where('date', '>=', now()->toDateString())
                       ->where('date', '<=', $cutoff);
                })->with('events');
            }])
            ->get();

        $mapped = $schedules->map(function ($schedule) {
            return [
                'id'               => $schedule->id,
                'name'             => $schedule->name,
                'description'      => $schedule->description,
                'frequency'        => $schedule->frequency,
                'location_name'    => $schedule->location_name,
                'location_address' => $schedule->location_address,
                'active'           => $schedule->active,
                'upcoming_rehearsals' => $schedule->rehearsals->map(fn($r) => $this->formatRehearsalSummary($r))->values()->all(),
            ];
        });

        return response()->json(['schedules' => $mapped->values()]);
    }

    /**
     * GET /api/mobile/rehearsals/{rehearsal}
     *
     * Return full detail for a single rehearsal. Band is derived from the rehearsal.
     */
    public function show(Request $request, int $rehearsal): JsonResponse
    {
        $rehearsalModel = Rehearsal::with(['rehearsalSchedule.band', 'events', 'bookings'])
            ->findOrFail($rehearsal);

        $band = $rehearsalModel->rehearsalSchedule?->band ?? $rehearsalModel->band;

        if (!$band) {
            abort(404, 'Band not found for this rehearsal.');
        }

        if (!$request->user()->canRead('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to read this rehearsal.');
        }

        return $this->rehearsalResponse($rehearsalModel);
    }

    /**
     * GET /api/mobile/rehearsals/by-key/{key}
     *
     * Resolve a virtual rehearsal key (e.g. "virtual-rehearsal-{scheduleId}-{date}")
     * to a real Rehearsal record, creating one if it does not yet exist.
     * Also accepts plain rehearsal event keys stored on real Rehearsal events.
     */
    public function showByKey(Request $request, string $key): JsonResponse
    {
        // First try to resolve via an existing Event record with this key.
        $existingEvent = \App\Models\Events::with(['eventable.rehearsalSchedule.band', 'eventable.events', 'eventable.bookings'])
            ->where('key', $key)
            ->first();

        if ($existingEvent && $existingEvent->eventable instanceof Rehearsal) {
            $rehearsalModel = $existingEvent->eventable;
            $band = $rehearsalModel->rehearsalSchedule?->band ?? $rehearsalModel->band;

            if (!$band || !$request->user()->canRead('rehearsals', $band->id)) {
                abort(403, 'You do not have permission to read this rehearsal.');
            }

            return $this->rehearsalResponse($rehearsalModel);
        }

        // Parse virtual key: "virtual-rehearsal-{scheduleId}-{YYYY-MM-DD}"
        if (!preg_match('/^virtual-rehearsal-(\d+)-(\d{4}-\d{2}-\d{2})$/', $key, $matches)) {
            abort(404, 'Rehearsal not found.');
        }

        $scheduleId = (int) $matches[1];
        $date = $matches[2];

        $schedule = RehearsalSchedule::with('band')->findOrFail($scheduleId);
        $band = $schedule->band;

        if (!$band || !$request->user()->canRead('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to read this rehearsal.');
        }

        // Find an existing Rehearsal for this schedule + date, or create a stub.
        $rehearsalModel = Rehearsal::whereHas('events', function ($q) use ($date) {
            $q->where('date', $date);
        })->where('rehearsal_schedule_id', $scheduleId)->first();

        if (!$rehearsalModel) {
            // Create a Rehearsal record so notes can be attached to it.
            $rehearsalModel = Rehearsal::create([
                'rehearsal_schedule_id' => $scheduleId,
                'band_id'               => $band->id,
                'venue_name'            => $schedule->location_name,
                'venue_address'         => $schedule->location_address,
                'is_cancelled'          => false,
                'notes'                 => null,
            ]);

            // Create the Events record that anchors the date and key so
            // subsequent calls to showByKey find this record instead of
            // creating another stub.
            $rehearsalModel->events()->create([
                'key'           => $key,
                'date'          => $date,
                'title'         => $schedule->name,
                'event_type_id' => EventTypes::where('name', 'Rehearsal')->value('id'),
            ]);
        }

        // Eager-load relationships needed for the response.
        $rehearsalModel->load(['rehearsalSchedule', 'events', 'bookings']);

        return $this->rehearsalResponse($rehearsalModel, $date);
    }

    /**
     * PATCH /api/mobile/rehearsals/{rehearsal}/notes
     *
     * Update the notes field on a rehearsal.
     */
    public function updateNotes(Request $request, int $rehearsal): JsonResponse
    {
        $rehearsalModel = Rehearsal::with('rehearsalSchedule.band')->findOrFail($rehearsal);

        $band = $rehearsalModel->rehearsalSchedule?->band ?? $rehearsalModel->band;

        if (!$band) {
            abort(404, 'Band not found for this rehearsal.');
        }

        if (!$request->user()->canWrite('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to update this rehearsal.');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $notes = isset($validated['notes']) && $validated['notes'] !== '' ? $validated['notes'] : null;
        $rehearsalModel->update(['notes' => $notes]);

        return response()->json(['notes' => $rehearsalModel->fresh()->notes]);
    }

    private function rehearsalResponse(Rehearsal $rehearsalModel, ?string $fallbackDate = null): JsonResponse
    {
        $event = $rehearsalModel->events->first();
        $date = $event
            ? (is_string($event->date) ? $event->date : $event->date->format('Y-m-d'))
            : $fallbackDate;
        $time = $event && $event->time
            ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
            : null;

        $schedule = $rehearsalModel->rehearsalSchedule;

        $associatedBookings = $rehearsalModel->bookings->map(function ($booking) {
            $bookingDate = is_string($booking->date) ? $booking->date : $booking->date->format('Y-m-d');
            return [
                'id'   => $booking->id,
                'name' => $booking->name,
                'date' => $bookingDate,
            ];
        })->values()->all();

        return response()->json([
            'rehearsal' => [
                'id'            => $rehearsalModel->id,
                'date'          => $date,
                'time'          => $time,
                'venue_name'    => $rehearsalModel->venue_name,
                'venue_address' => $rehearsalModel->venue_address,
                'is_cancelled'  => $rehearsalModel->is_cancelled,
                'notes'         => $rehearsalModel->notes,
                'event_key'     => $event?->key,
                'schedule'      => $schedule ? [
                    'id'            => $schedule->id,
                    'name'          => $schedule->name,
                    'location_name' => $schedule->location_name,
                ] : null,
                'associated_bookings' => $associatedBookings,
            ],
        ]);
    }

    private function formatRehearsalSummary(Rehearsal $rehearsal): array
    {
        $event = $rehearsal->events->first();
        $date = $event
            ? (is_string($event->date) ? $event->date : $event->date->format('Y-m-d'))
            : null;
        $time = $event && $event->time
            ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
            : null;

        return [
            'id'            => $rehearsal->id,
            'date'          => $date,
            'time'          => $time,
            'venue_name'    => $rehearsal->venue_name,
            'venue_address' => $rehearsal->venue_address,
            'is_cancelled'  => $rehearsal->is_cancelled,
            'notes'         => $rehearsal->notes,
            'event_key'     => $event?->key,
        ];
    }
}
