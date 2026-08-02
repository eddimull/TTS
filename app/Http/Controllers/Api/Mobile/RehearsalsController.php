<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\SetRehearsalCancelledRequest;
use App\Http\Requests\Mobile\UpdateRehearsalNotesRequest;
use App\Jobs\ProcessRehearsalCancelled;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Services\Mobile\RecurrenceLabelService;
use App\Services\Mobile\RehearsalService;
use App\Services\RehearsalScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RehearsalsController extends Controller
{
    /** Furthest forward a client may ask the schedule window to reach. */
    private const MAX_FORWARD_YEARS = 6;

    /** Default schedule window when `until` is absent or malformed. */
    private const DEFAULT_WINDOW_DAYS = 60;

    public function __construct(private readonly RehearsalService $rehearsalService) {}

    /**
     * Parse a client-supplied date, accepting only strict Y-m-d, and cap it at
     * the forward horizon.
     *
     * Carbon::parse() is deliberately avoided: it throws on true garbage (a
     * 500) and silently rolls over garbage-adjacent input like 2026-02-31.
     *
     * @return Carbon|null null when absent or malformed — caller falls back to the default
     */
    private static function parseForwardBound(?string $value): ?Carbon
    {
        if (! is_string($value) || $value === '' || ! Carbon::hasFormat($value, 'Y-m-d')) {
            return null;
        }

        $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();

        // hasFormat() validates the pattern, not the calendar: 2026-02-31 passes
        // and then silently rolls over to 2026-03-03. Require a round-trip.
        if ($date->toDateString() !== $value) {
            return null;
        }

        $horizon = Carbon::now()->addYears(self::MAX_FORWARD_YEARS);

        return $date->greaterThan($horizon) ? $horizon : $date;
    }

    /**
     * GET /api/mobile/bands/{band}/rehearsal-schedules
     *
     * List all rehearsal schedules for a band with upcoming rehearsals.
     * Window: today .. `until` (inclusive, default +60 days). With
     * `include_virtual=1`, un-materialized occurrences generated from the
     * schedule's recurrence rule are merged in (id: null, event_key:
     * "virtual-rehearsal-{scheduleId}-{date}"). Both params are opt-in so the
     * default response stays byte-compatible for old clients.
     */
    public function schedules(Request $request): JsonResponse
    {
        $band           = $request->input('mobile_band');
        $user           = $request->user();
        // A user who can only read via the rehearsal-sub carve-out sees just
        // the rehearsals they're invited to — no virtuals, no full schedule.
        $subScoped      = !$user->canReadRehearsalsAsMember($band->id);
        $includeVirtual = $request->boolean('include_virtual') && !$subScoped;
        // A malformed `until` falls back to the default window rather than 500ing.
        $cutoff = (self::parseForwardBound($request->input('until'))
            ?? now()->addDays(self::DEFAULT_WINDOW_DAYS))->toDateString();

        $schedules = RehearsalSchedule::where('band_id', $band->id)
            ->with(['rehearsals' => function ($query) use ($cutoff, $subScoped, $user) {
                $query->whereHas('events', function ($eq) use ($cutoff) {
                    $eq->where('date', '>=', now()->toDateString())
                       ->where('date', '<=', $cutoff);
                })->with('events');

                if ($subScoped) {
                    $query->whereHas('subs', fn ($sq) => $sq->where('user_id', $user->id));
                }
            }])
            ->get();

        $virtualBySchedule = collect();
        if ($includeVirtual) {
            // endOfDay so the inclusive `until` date survives the generators'
            // exclusive `lt($endDate)` loops.
            $virtualBySchedule = (new RehearsalScheduleService())
                ->generateUpcomingRehearsals([$band->id], now(), Carbon::parse($cutoff)->endOfDay())
                ->groupBy('rehearsal_schedule_id');
        }

        $labels = new RecurrenceLabelService();

        $mapped = $schedules->map(function ($schedule) use ($labels, $includeVirtual, $virtualBySchedule) {
            $upcoming = $schedule->rehearsals
                ->map(fn ($r) => $this->rehearsalService->formatSummary($r)
                    + ($includeVirtual ? ['is_virtual' => false] : []));

            if ($includeVirtual) {
                $virtuals = ($virtualBySchedule[$schedule->id] ?? collect())->map(fn ($v) => [
                    'id'            => null,
                    'date'          => $v['date'],
                    'time'          => substr((string) $v['time'], 0, 5),
                    'venue_name'    => $v['venue_name'],
                    'venue_address' => $v['venue_address'],
                    'is_cancelled'  => false,
                    'notes'         => null,
                    'event_key'     => $v['key'],
                    'is_virtual'    => true,
                ]);
                $upcoming = $upcoming->concat($virtuals)->sortBy('date')->values();
            }

            return [
                'id'                  => $schedule->id,
                'name'                => $schedule->name,
                'description'         => $schedule->description,
                'frequency'           => $schedule->frequency,
                'recurrence_label'    => $labels->format($schedule),
                'location_name'       => $schedule->location_name,
                'location_address'    => $schedule->location_address,
                'active'              => $schedule->active,
                'upcoming_rehearsals' => $upcoming->values()->all(),
            ];
        });

        if ($subScoped) {
            $mapped = $mapped->filter(fn ($s) => count($s['upcoming_rehearsals']) > 0);
        }

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

        if (!$request->user()->canReadRehearsalsAsMember($band->id)
            && !$rehearsalModel->subs()->where('user_id', $request->user()->id)->exists()) {
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

            if (!$request->user()->canReadRehearsalsAsMember($band->id)
                && !$rehearsalModel->subs()->where('user_id', $request->user()->id)->exists()) {
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

        // Virtual materialization is member-only: a sub's carve-out grants access
        // to their own invited rehearsals, never to creating new ones.
        if (!$request->user()->canReadRehearsalsAsMember($band->id)) {
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
                    now()->getPreciseTimestamp(3),
                ),
            );
        }

        $rehearsalModel->load(['rehearsalSchedule', 'events', 'bookings']);

        return response()->json([
            'rehearsal' => $this->rehearsalService->formatDetail($rehearsalModel),
        ]);
    }
}
