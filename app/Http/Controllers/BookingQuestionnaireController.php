<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendQuestionnaireRequest;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Questionnaires;
use App\Models\QuestionnaireInstances;
use App\Notifications\QuestionnaireSent;
use App\Services\QuestionnaireSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BookingQuestionnaireController extends Controller
{
    public function __construct(private QuestionnaireSnapshotService $snapshotService)
    {
    }

    public function send(SendQuestionnaireRequest $request, Bands $band, Bookings $booking): RedirectResponse
    {
        abort_if($booking->band_id !== $band->id, 404);

        $template = Questionnaires::findOrFail($request->input('questionnaire_id'));
        $contact = Contacts::findOrFail($request->input('recipient_contact_id'));

        $instance = $this->snapshotService->snapshot($template, $booking, $contact, Auth::user());
        $contact->notify(new QuestionnaireSent($instance));

        return back()->with('success', "Questionnaire sent to {$contact->name}.");
    }

    public function resend(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);

        $instance->recipientContact->notify(new QuestionnaireSent($instance));
        return back()->with('success', 'Questionnaire email re-sent.');
    }

    public function lock(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);

        $instance->update([
            'status' => QuestionnaireInstances::STATUS_LOCKED,
            'locked_at' => now(),
            'locked_by_user_id' => Auth::id(),
        ]);
        return back()->with('success', 'Questionnaire locked.');
    }

    public function unlock(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);

        $hasResponses = $instance->responses()->exists();
        $instance->update([
            'status' => $instance->submitted_at
                ? QuestionnaireInstances::STATUS_SUBMITTED
                : ($hasResponses ? QuestionnaireInstances::STATUS_IN_PROGRESS : QuestionnaireInstances::STATUS_SENT),
            'locked_at' => null,
            'locked_by_user_id' => null,
        ]);
        return back()->with('success', 'Questionnaire unlocked.');
    }

    public function destroy(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);
        $instance->delete();
        return back()->with('success', 'Questionnaire deleted.');
    }

    private function authorizeAccess(Bands $band, Bookings $booking, QuestionnaireInstances $instance): void
    {
        abort_unless(Auth::user()->canWrite('questionnaires', $band->id), 403);
        abort_if($booking->band_id !== $band->id, 404);
        abort_if($instance->booking_id !== $booking->id, 404);
    }
}
