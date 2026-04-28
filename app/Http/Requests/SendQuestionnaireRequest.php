<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $band = $this->route('band');
        return $this->user()->canWrite('questionnaires', $band->id);
    }

    public function rules(): array
    {
        $band = $this->route('band');
        $booking = $this->route('booking');

        return [
            'questionnaire_id' => [
                'required',
                'integer',
                Rule::exists('questionnaires', 'id')
                    ->where(fn ($q) => $q->where('band_id', $band->id)->whereNull('archived_at')->whereNull('deleted_at')),
            ],
            'recipient_contact_id' => [
                'required',
                'integer',
                Rule::exists('booking_contacts', 'contact_id')
                    ->where(fn ($q) => $q->where('booking_id', $booking->id)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $contactId = $this->input('recipient_contact_id');
            if (!$contactId) {
                return;
            }

            $contact = \App\Models\Contacts::find($contactId);
            if ($contact && !$contact->can_login) {
                $v->errors()->add('recipient_contact_id', 'This contact does not have portal access enabled. Enable it before sending a questionnaire.');
            }
        });
    }
}
