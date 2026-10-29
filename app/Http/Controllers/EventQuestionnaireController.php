<?php

namespace App\Http\Controllers;

use App\Exceptions\QuestionnaireRoutingException;
use App\Http\Controllers\Api\Mobile\QuestionnaireInstancesController;
use App\Http\Requests\RouteQuestionnaireRequest;
use App\Models\Events;
use App\Models\QuestionnaireApplications;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Services\QuestionnaireMappingService;
use App\Services\QuestionnaireRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class EventQuestionnaireController extends Controller
{
    public function __construct(
        private QuestionnaireMappingService $mappingService,
        private QuestionnaireRoutingService $routingService,
    ) {
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

    /**
     * JSON preview for the event editor's "Send to Event" review. Same
     * payload as the mobile routing endpoint.
     */
    public function routing(Events $event, QuestionnaireInstances $instance): JsonResponse
    {
        $this->authorizeAccess($event, $instance);

        try {
            return response()->json($this->routingService->preview($instance, $event));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function routeAnswers(RouteQuestionnaireRequest $request, Events $event, QuestionnaireInstances $instance): JsonResponse
    {
        $this->authorizeAccess($event, $instance);

        // "Remember for this questionnaire" edits the template.
        if ($request->remembersAny()) {
            abort_unless(Auth::user()->canWrite('questionnaires', $instance->booking->band_id), 403);
        }

        try {
            $result = $this->routingService->route($instance, $request->items(), $request->resolutions(), Auth::user(), $event);
        } catch (QuestionnaireRoutingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'application_id' => $result['application']->id,
            'applied' => $result['applied'],
            'event' => QuestionnaireInstancesController::routedEvent($result['event']),
        ]);
    }

    public function undo(Events $event, QuestionnaireApplications $application): JsonResponse
    {
        $instance = $application->instance()->withTrashed()->first();
        abort_if(!$instance || (int) $application->event_id !== (int) $event->id, 404);
        $this->authorizeAccess($event, $instance);

        try {
            $restored = $this->routingService->undo($application);
        } catch (QuestionnaireRoutingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'application_id' => $application->id,
            'undone' => true,
            'event' => QuestionnaireInstancesController::routedEvent($restored),
        ]);
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
