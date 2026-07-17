<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendQuestionnaireRequest;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\Questionnaires;
use App\Notifications\QuestionnaireSent;
use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnaireMappingService;
use App\Services\QuestionnaireResponsePresenter;
use App\Services\QuestionnaireSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/** Authorization is handled at the route layer via the mobile.band middleware. */
class QuestionnaireInstancesController extends Controller
{
    public function __construct(
        private QuestionnaireResponsePresenter $presenter,
        private QuestionnaireSnapshotService $snapshotService,
        private QuestionnaireMappingRegistry $mappingRegistry,
        private QuestionnaireMappingService $mappingService,
    ) {
    }

    public function instancesForQuestionnaire(Bands $band, Questionnaires $questionnaire): JsonResponse
    {
        abort_if($questionnaire->band_id !== $band->id, 404);

        $instances = $questionnaire->instances()
            ->with(['recipientContact:id,name', 'booking:id,name,band_id'])
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn (QuestionnaireInstances $i) => $this->summary($i));

        return response()->json(['instances' => $instances]);
    }

    public function eligibleBookings(Bands $band, Questionnaires $questionnaire): JsonResponse
    {
        abort_if($questionnaire->band_id !== $band->id, 404);

        $sentBookingIds = $questionnaire->instances()->pluck('booking_id')->all();

        $bookings = $band->bookings()
            ->with(['contacts:id,name,can_login', 'events:id,eventable_id,eventable_type,date,venue_name'])
            ->whereHas('events', fn ($q) => $q->whereDate('date', '>=', today()))
            ->get(['id', 'name', 'band_id'])
            ->map(fn (Bookings $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'date' => $b->event_dates,
                'already_sent' => in_array($b->id, $sentBookingIds, true),
                'contacts' => $b->contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'is_primary' => (bool) ($c->pivot->is_primary ?? false),
                    'can_login' => (bool) $c->can_login,
                ])->values(),
            ]);

        return response()->json(['bookings' => $bookings]);
    }

    public function forBooking(Bands $band, Bookings $booking): JsonResponse
    {
        abort_if($booking->band_id !== $band->id, 404);

        $instances = $booking->questionnaireInstances()
            ->with(['recipientContact:id,name', 'booking:id,name,band_id'])
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn (QuestionnaireInstances $i) => $this->summary($i));

        $available = $band->questionnaires()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($q) => ['id' => $q->id, 'name' => $q->name]);

        return response()->json([
            'instances' => $instances,
            'available_questionnaires' => $available,
        ]);
    }

    public function show(Bands $band, QuestionnaireInstances $instance): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);
        $instance->load([
            'recipientContact:id,name',
            'booking:id,name,band_id',
            'fields',
            'responses',
        ]);

        return response()->json(['instance' => $this->detail($instance, $band)]);
    }

    public function send(SendQuestionnaireRequest $request, Bands $band, Bookings $booking): JsonResponse
    {
        abort_if($booking->band_id !== $band->id, 404);

        $template = Questionnaires::findOrFail($request->input('questionnaire_id'));
        $contact = Contacts::findOrFail($request->input('recipient_contact_id'));

        $instance = $this->snapshotService->snapshot($template, $booking, $contact, Auth::user());
        $contact->notify(new QuestionnaireSent($instance));

        $instance->load(['recipientContact:id,name', 'booking:id,name,band_id']);

        return response()->json(['instance' => $this->summary($instance)], 201);
    }

    public function resend(Bands $band, QuestionnaireInstances $instance): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);

        $instance->recipientContact->notify(new QuestionnaireSent($instance));
        $instance->load(['recipientContact:id,name', 'booking:id,name,band_id']);

        return response()->json(['instance' => $this->summary($instance)]);
    }

    public function lock(Bands $band, QuestionnaireInstances $instance): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);

        $instance->update([
            'status' => QuestionnaireInstances::STATUS_LOCKED,
            'locked_at' => now(),
            'locked_by_user_id' => Auth::id(),
        ]);
        $instance->load(['recipientContact:id,name', 'booking:id,name,band_id']);

        return response()->json(['instance' => $this->summary($instance)]);
    }

    public function unlock(Bands $band, QuestionnaireInstances $instance): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);

        $hasResponses = $instance->responses()->exists();
        $instance->update([
            'status' => $instance->submitted_at
                ? QuestionnaireInstances::STATUS_SUBMITTED
                : ($hasResponses ? QuestionnaireInstances::STATUS_IN_PROGRESS : QuestionnaireInstances::STATUS_SENT),
            'locked_at' => null,
            'locked_by_user_id' => null,
        ]);
        $instance->load(['recipientContact:id,name', 'booking:id,name,band_id']);

        return response()->json(['instance' => $this->summary($instance)]);
    }

    public function destroy(Bands $band, QuestionnaireInstances $instance): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);
        $instance->delete();

        return response()->json(['message' => 'Questionnaire instance deleted']);
    }

    public function applyResponse(Bands $band, QuestionnaireInstances $instance, QuestionnaireResponses $response): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);
        abort_unless(Auth::user()->canRead('questionnaires', $band->id), 403);
        abort_if($response->instance_id !== $instance->id, 404);

        try {
            $this->mappingService->applyResponse($response, Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $response->refresh();

        return response()->json([
            'response' => [
                'response_id' => $response->id,
                'applied_to_event_at' => $response->applied_to_event_at?->toIso8601String(),
                'updated_at' => $response->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function applyAll(Bands $band, QuestionnaireInstances $instance): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);
        abort_unless(Auth::user()->canRead('questionnaires', $band->id), 403);

        $pending = $instance->responses()
            ->whereHas('instanceField', fn ($q) => $q->whereNotNull('mapping_target'))
            ->whereNull('applied_to_event_at')
            ->get();

        $applied = 0;
        try {
            foreach ($pending as $pendingResponse) {
                $this->mappingService->applyResponse($pendingResponse, Auth::user());
                $applied++;
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'applied_count' => $applied], 422);
        }

        return response()->json(['applied_count' => $applied]);
    }

    public function appendToNotes(Bands $band, QuestionnaireInstances $instance): JsonResponse
    {
        $this->ensureBelongsToBand($band, $instance);
        abort_unless(Auth::user()->canRead('questionnaires', $band->id), 403);

        try {
            $this->mappingService->appendAllToNotes($instance, Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Answers appended to event notes.']);
    }

    private function ensureBelongsToBand(Bands $band, QuestionnaireInstances $instance): void
    {
        abort_if($instance->booking?->band_id !== $band->id, 404);
    }

    private function summary(QuestionnaireInstances $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'status' => $i->status,
            'sent_at' => $i->sent_at?->toIso8601String(),
            'submitted_at' => $i->submitted_at?->toIso8601String(),
            'recipient_name' => $i->recipientContact->name ?? 'Unknown',
            'booking' => [
                'id' => $i->booking->id,
                'name' => $i->booking->name,
            ],
            'questionnaire_id' => $i->questionnaire_id,
        ];
    }

    private function detail(QuestionnaireInstances $i, Bands $band): array
    {
        return $this->summary($i) + [
            'description' => $i->description,
            'first_opened_at' => $i->first_opened_at?->toIso8601String(),
            'locked_at' => $i->locked_at?->toIso8601String(),
            'fields' => $i->fields->map(fn ($f) => [
                'id' => $f->id,
                'type' => $f->type,
                'label' => $f->label,
                'help_text' => $f->help_text,
                'required' => (bool) $f->required,
                'position' => $f->position,
                'settings' => $f->settings,
                'visibility_rule' => $f->visibility_rule,
                'mapping_target' => $f->mapping_target,
                'mapping_label' => $f->mapping_target && $this->mappingRegistry->targetExists($f->mapping_target)
                    ? $this->mappingRegistry->label($f->mapping_target)
                    : null,
            ])->values()->all(),
            'responses' => (object) $i->responses->mapWithKeys(fn ($r) => [
                $r->instance_field_id => $this->presenter->decode($r->value),
            ])->all(),
            'response_meta' => (object) $i->responses->mapWithKeys(fn ($r) => [
                $r->instance_field_id => [
                    'response_id' => $r->id,
                    'applied_to_event_at' => $r->applied_to_event_at?->toIso8601String(),
                    'updated_at' => $r->updated_at?->toIso8601String(),
                ],
            ])->all(),
            'song_lookup' => (object) $this->presenter->songLookup([$i], $band->id),
        ];
    }
}
