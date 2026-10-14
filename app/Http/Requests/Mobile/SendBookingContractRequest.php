<?php

namespace App\Http\Requests\Mobile;

use App\Http\Requests\Concerns\ResolvesContractRecipients;
use App\Models\Contacts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class SendBookingContractRequest extends FormRequest
{
    use ResolvesContractRecipients;

    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    /**
     * Installed app versions still send a single `signer_id` / `cc_id`.
     */
    protected function prepareForValidation(): void
    {
        $this->foldLegacyField('signer_id', 'signer_ids');
        $this->foldLegacyField('cc_id', 'cc_ids');
    }

    public function rules(): array
    {
        return [
            'signer_ids'   => ['required', 'array', 'min:1'],
            'signer_ids.*' => ['required', 'integer', 'distinct', $this->bookingContactRule()],
            'cc_ids'       => ['nullable', 'array'],
            'cc_ids.*'     => ['integer', 'distinct', $this->bookingContactRule()],
        ];
    }

    public function messages(): array
    {
        return [
            'signer_ids.required' => 'At least one signer is required.',
            'signer_ids.min' => 'At least one signer is required.',
            'signer_ids.*.distinct' => 'Each signer can only be selected once.',
            'signer_ids.*.in' => 'Signer contact not found on this booking.',
            'cc_ids.*.in' => 'CC contact not found on this booking.',
        ];
    }

    /**
     * @return Collection<int, Contacts>
     */
    public function signerContacts(): Collection
    {
        return $this->bookingContactsInOrder($this->validated('signer_ids', []));
    }

    /**
     * CC contacts, minus anyone who is already signing.
     *
     * @return Collection<int, Contacts>
     */
    public function ccContacts(): Collection
    {
        $signerIds = $this->signerContacts()->pluck('id')->all();

        return $this->bookingContactsInOrder($this->validated('cc_ids') ?? [])
            ->reject(fn (Contacts $contact) => in_array($contact->id, $signerIds, true))
            ->values();
    }
}
