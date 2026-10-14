<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesContractRecipients;
use App\Models\Contacts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class SendBookingContractRequest extends FormRequest
{
    use ResolvesContractRecipients;

    public function authorize()
    {
        return true; // Access is enforced by the booking.access route middleware
    }

    /**
     * `signer` / a scalar `cc` are the pre-multi-signer payload; keep accepting them.
     */
    protected function prepareForValidation(): void
    {
        $this->foldLegacyField('signer', 'signers');

        if ($this->filled('cc') && !is_array($this->input('cc'))) {
            $this->merge(['cc' => [$this->input('cc')]]);
        }
    }

    public function rules()
    {
        return [
            'signers'   => ['required', 'array', 'min:1'],
            'signers.*' => ['required', 'integer', 'distinct', $this->bookingContactRule()],
            'cc'        => ['nullable', 'array'],
            'cc.*'      => ['integer', 'distinct', $this->bookingContactRule()],
        ];
    }

    public function messages()
    {
        return [
            'signers.required' => 'At least one signer is required.',
            'signers.min' => 'At least one signer is required.',
            'signers.*.distinct' => 'Each signer can only be selected once.',
            'signers.*.in' => 'Every signer must be a contact on this booking.',
            'cc.array' => 'CC must be an array of contact IDs.',
            'cc.*.in' => 'One or more CC contacts are not valid.',
        ];
    }

    /**
     * @return Collection<int, Contacts>
     */
    public function signerContacts(): Collection
    {
        return $this->bookingContactsInOrder($this->validated('signers', []));
    }

    /**
     * CC contacts, minus anyone who is already signing.
     *
     * @return Collection<int, Contacts>
     */
    public function ccContacts(): Collection
    {
        $signerIds = $this->signerContacts()->pluck('id')->all();

        return $this->bookingContactsInOrder($this->validated('cc') ?? [])
            ->reject(fn (Contacts $contact) => in_array($contact->id, $signerIds, true))
            ->values();
    }
}
