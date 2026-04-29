<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Services\QuestionnaireMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class EventQuestionnaireController extends Controller
{
    public function __construct(private QuestionnaireMappingService $mappingService)
    {
    }

    public function applyResponse(Events $event, QuestionnaireInstances $instance, QuestionnaireResponses $response): RedirectResponse
    {
        $this->authorizeAccess($event, $instance);
        abort_if($response->instance_id !== $instance->id, 404);

        $this->mappingService->applyResponse($response, Auth::user());

        return back()->with('success', 'Answer applied to event.');
    }

    public function applyAll(Events $event, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($event, $instance);

        $instance->responses()
            ->whereHas('instanceField', fn ($q) => $q->whereNotNull('mapping_target'))
            ->whereNull('applied_to_event_at')
            ->each(fn ($r) => $this->mappingService->applyResponse($r, Auth::user()));

        return back()->with('success', 'All pending answers applied.');
    }

    public function appendToNotes(Events $event, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($event, $instance);

        $this->mappingService->appendAllToNotes($instance, Auth::user());

        return back()->with('success', 'Answers appended to event notes.');
    }

    private function authorizeAccess(Events $event, QuestionnaireInstances $instance): void
    {
        $user = Auth::user();
        $booking = $instance->booking;

        abort_if($instance->booking_id !== $event->eventable_id || $event->eventable_type !== \App\Models\Bookings::class, 404);
        abort_unless($user->canRead('questionnaires', $booking->band_id), 403);
        abort_unless($user->canWrite('events', $booking->band_id), 403);
    }
}
