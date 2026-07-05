<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\SetRehearsalCancelledRequest;
use App\Http\Requests\Mobile\UpdateRehearsalNotesRequest;
use App\Jobs\ProcessRehearsalCancelled;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Services\Mobile\RehearsalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RehearsalsController extends Controller
{
    public function __construct(private readonly RehearsalService $rehearsalService) {}

    /**
     * GET /api/mobile/bands/{band}/rehearsal-schedules
     *
     * List all rehearsal schedules for a band with upcoming rehearsals (next 60 days).
     */
    public function schedules(Request $request): JsonResponse
    {
        $band   = $request->input('mobile_band');
        $cutoff = now()->addDays(60)->toDateString();

        $schedules = RehearsalSchedule::where('band_id', $band->id)
            ->with(['rehearsals' => function ($query) use ($cutoff) {
                $query->whereHas('events', function ($eq) use ($cutoff) {
                    $eq->where('date', '>=', now()->toDateString())
                       ->where('date', '<=', $cutoff);
                })->with('events');
            }])
            ->get();

        $mapped = $schedules->map(fn ($schedule) => [
            'id'               => $schedule->id,
            'name'             => $schedule->name,
            'description'      => $schedule->description,
            'frequency'        => $schedule->frequency,
            'location_name'    => $schedule->location_name,
            'location_address' => $schedule->location_address,
            'active'           => $schedule->active,
            'upcoming_rehearsals' => $schedule->rehearsals
                ->map(fn ($r) => $this->rehearsalService->formatSummary($r))
                ->values()->all(),
        ]);

        return response()->json(['schedules' => $mapped->values()]);
    }

    /**
     * GET /api/mobile/rehearsals/{rehearsal}
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
            abort(403, 'You do not have permission to view this rehearsal.');
        }

        return response()->json([
            'rehearsal' => $this->rehearsalService->formatDetail($rehearsalModel),
        ]);
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

            if (!$band) {
                abort(404, 'Band not found for this rehearsal.');
            }

            if (!$request->user()->canRead('rehearsals', $band->id)) {
                abort(403, 'You do not have permission to view this rehearsal.');
            }

            return response()->json([
                'rehearsal' => $this->rehearsalService->formatDetail($rehearsalModel),
            ]);
        }

        [$scheduleId, $date] = $this->rehearsalService->parseVirtualKey($key);

        $schedule = RehearsalSchedule::with('band')->findOrFail($scheduleId);
        $band     = $schedule->band;

        if (!$band) {
            abort(404, 'Band not found for this rehearsal.');
        }

        if (!$request->user()->canRead('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to view this rehearsal.');
        }

        $rehearsalModel = $this->rehearsalService->findOrCreateStub($schedule, $date, $key);
        $rehearsalModel->load(['rehearsalSchedule', 'events', 'bookings']);

        return response()->json([
            'rehearsal' => $this->rehearsalService->formatDetail($rehearsalModel, $date),
        ]);
    }

    /**
     * PATCH /api/mobile/rehearsals/{rehearsal}/notes
     */
    public function updateNotes(UpdateRehearsalNotesRequest $request, int $rehearsal): JsonResponse
    {
        $rehearsalModel = Rehearsal::with('rehearsalSchedule.band')->findOrFail($rehearsal);

        $band = $rehearsalModel->rehearsalSchedule?->band ?? $rehearsalModel->band;

        if (!$band) {
            abort(404, 'Band not found for this rehearsal.');
        }

        if (!$request->user()->canWrite('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to edit this rehearsal.');
        }

        $validated = $request->validated();
        $notes     = isset($validated['notes']) && $validated['notes'] !== '' ? $validated['notes'] : null;
        $rehearsalModel->update(['notes' => $notes]);

        return response()->json(['notes' => $rehearsalModel->fresh()->notes]);
    }

    /**
     * PATCH /api/mobile/rehearsals/{rehearsal}/cancelled
     *
     * Explicitly set (not toggle) the cancelled flag. Idempotent: setting the
     * current value succeeds without notifying the band again.
     */
    public function setCancelled(SetRehearsalCancelledRequest $request, int $rehearsal): JsonResponse
    {
        $rehearsalModel = Rehearsal::with(['rehearsalSchedule.band', 'events', 'bookings'])
            ->findOrFail($rehearsal);

        $band = $rehearsalModel->rehearsalSchedule?->band ?? $rehearsalModel->band;

        if (!$band) {
            abort(404, 'Band not found for this rehearsal.');
        }

        if (!$request->user()->canWrite('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to edit this rehearsal.');
        }

        $isCancelled = (bool) $request->validated()['is_cancelled'];

        if ($rehearsalModel->is_cancelled !== $isCancelled) {
            $rehearsalModel->update(['is_cancelled' => $isCancelled]);
            $rehearsalModel->refresh();

            ProcessRehearsalCancelled::dispatch(
                $rehearsalModel,
                $request->user()->id,
                $isCancelled,
                sprintf(
                    'rehearsal:%d:%s:%s',
                    $rehearsalModel->id,
                    $isCancelled ? 'cancelled' : 'restored',
                    $rehearsalModel->updated_at->getPreciseTimestamp(3),
                ),
            );
        }

        $rehearsalModel->load(['rehearsalSchedule', 'events', 'bookings']);

        return response()->json([
            'rehearsal' => $this->rehearsalService->formatDetail($rehearsalModel),
        ]);
    }
}
