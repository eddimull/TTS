<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveResponseRequest;
use App\Models\Bookings;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalQuestionnaireController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:contact');
    }

    public function show(Bookings $booking, QuestionnaireInstances $instance): Response
    {
        $this->authorizeAccess($booking, $instance);

        if ($instance->first_opened_at === null) {
            $instance->update(['first_opened_at' => now()]);
        }

        $fields = $instance->fields()->orderBy('position')->get();
        $responses = $instance->responses()->get()->mapWithKeys(
            fn ($r) => [$r->instance_field_id => $this->decodeValue($r->value)]
        );

        return Inertia::render('Contact/Questionnaire/Show', [
            'booking' => [
                'id' => $booking->id,
                'name' => $booking->name,
                'date' => $booking->date->format('M j, Y'),
                'band_name' => $booking->band->name,
            ],
            'instance' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'description' => $instance->description,
                'status' => $instance->status,
                'submitted_at' => $instance->submitted_at?->format('M j, Y'),
                'is_locked' => $instance->isLocked(),
            ],
            'fields' => $fields,
            'responses' => $responses,
        ]);
    }

    public function saveResponse(SaveResponseRequest $request, Bookings $booking, QuestionnaireInstances $instance): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAccess($booking, $instance);
        abort_if($instance->isLocked(), 403, 'This questionnaire is locked.');

        $field = QuestionnaireInstanceFields::findOrFail($request->input('instance_field_id'));
        $value = $this->encodeValue($request->input('value'), $field->type);

        QuestionnaireResponses::updateOrCreate(
            [
                'instance_id' => $instance->id,
                'instance_field_id' => $field->id,
            ],
            ['value' => $value]
        );

        if ($instance->status === QuestionnaireInstances::STATUS_SENT) {
            $instance->update(['status' => QuestionnaireInstances::STATUS_IN_PROGRESS]);
        }

        return response()->json(['saved_at' => now()->toIso8601String()]);
    }

    /**
     * Auth check: contact must be on the booking, and the instance must belong to it.
     */
    private function authorizeAccess(Bookings $booking, QuestionnaireInstances $instance): void
    {
        $contact = Auth::guard('contact')->user();
        abort_if($instance->booking_id !== $booking->id, 404);
        abort_unless($booking->contacts->contains('id', $contact->id), 403);
    }

    /**
     * Multi-value responses are JSON-encoded arrays. Decode for Vue.
     */
    private function decodeValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * Multi-value field types (multi_select, checkbox_group) JSON-encode their array.
     * Other types coerce to string.
     */
    private function encodeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        if (in_array($type, ['multi_select', 'checkbox_group'], true)) {
            return is_array($value) ? json_encode(array_values($value)) : json_encode([$value]);
        }
        return is_array($value) ? implode(',', $value) : (string) $value;
    }
}
