<?php

namespace App\Http\Requests;

use App\Models\Lodging;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLodgingWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller checks canWrite('lodging', $lodging->band_id)
    }

    public function rules(): array
    {
        // PATCH semantics: everything optional, validated when present.
        return [
            'name'                        => 'sometimes|required|string|max:255',
            'address'                     => 'sometimes|nullable|string|max:255',
            'latitude'                    => 'sometimes|nullable|numeric|between:-90,90',
            'longitude'                   => 'sometimes|nullable|numeric|between:-180,180',
            'check_in_at'                 => 'sometimes|required|date_format:Y-m-d H:i:s',
            'check_out_at'                => 'sometimes|required|date_format:Y-m-d H:i:s',
            'notes'                       => 'sometimes|nullable|string',
            'booking_id'                  => 'sometimes|nullable|integer|exists:bookings,id',
            'event_id'                    => 'sometimes|nullable|integer|exists:events,id',
            'rooms'                       => 'sometimes|array',
            'rooms.*.id'                  => 'sometimes|integer',
            'rooms.*.label'               => 'required|string|max:255',
            'rooms.*.confirmation_number' => 'nullable|string|max:255',
            'rooms.*.notes'               => 'nullable|string',
        ];
    }

    /**
     * PATCH semantics mean check_in_at/check_out_at can each be supplied
     * independently, so the `after:check_in_at` rule used on store() doesn't
     * apply here — it would false-fail whenever only one side is present in
     * this request. Instead: if check_out_at is supplied, compare it against
     * whichever check_in_at is in play — the one also in this request if
     * present, otherwise the stay's current stored value (from the route
     * binding) — so moving check_out_at alone earlier than the existing
     * check_in_at is still rejected.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->filled('check_out_at')) {
                return;
            }

            $checkIn = $this->input('check_in_at');
            if (!$checkIn) {
                /** @var Lodging|null $lodging */
                $lodging = $this->route('lodging');
                $checkIn = $lodging?->check_in_at;
            }

            if (!$checkIn) {
                return;
            }

            if (strtotime($this->input('check_out_at')) <= strtotime($checkIn)) {
                $validator->errors()->add('check_out_at', 'The check out at must be a date after check in at.');
            }
        });
    }
}
