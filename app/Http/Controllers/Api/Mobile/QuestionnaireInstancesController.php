<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Services\QuestionnaireResponsePresenter;
use Illuminate\Http\JsonResponse;

/** Authorization is handled at the route layer via the mobile.band middleware. */
class QuestionnaireInstancesController extends Controller
{
    public function __construct(
        private QuestionnaireResponsePresenter $presenter,
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
            ])->values()->all(),
            'responses' => (object) $i->responses->mapWithKeys(fn ($r) => [
                $r->instance_field_id => $this->presenter->decode($r->value),
            ])->all(),
            'song_lookup' => (object) $this->presenter->songLookup([$i], $band->id),
        ];
    }
}
