<?php

namespace App\Http\Requests\Concerns;

use App\Models\Bookings;
use App\Models\Contacts;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Shared by the web and mobile "send contract" requests: folds the legacy
 * single-signer/single-cc fields into the array fields, restricts every id to
 * the booking's own contacts, and resolves the validated ids to contacts in
 * the order they were given (that order decides signer1, signer2, ...).
 */
trait ResolvesContractRecipients
{
    /** @var array<int, int>|null */
    private ?array $bookingContactIdsCache = null;

    protected function routeBooking(): ?Bookings
    {
        $booking = $this->route('booking');

        return $booking instanceof Bookings ? $booking : null;
    }

    /**
     * @return array<int, int>
     */
    protected function bookingContactIds(): array
    {
        return $this->bookingContactIdsCache ??= $this->routeBooking()
            ?->contacts()
            ->pluck('contacts.id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];
    }

    /**
     * Copy a legacy scalar field into its array replacement when the client
     * did not send the array field itself.
     */
    protected function foldLegacyField(string $legacyKey, string $arrayKey): void
    {
        if ($this->has($arrayKey) || !$this->filled($legacyKey)) {
            return;
        }

        $legacy = $this->input($legacyKey);

        $this->merge([$arrayKey => is_array($legacy) ? array_values($legacy) : [$legacy]]);
    }

    protected function bookingContactRule(): \Illuminate\Validation\Rules\In
    {
        return Rule::in($this->bookingContactIds());
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Contacts>
     */
    protected function bookingContactsInOrder(array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty() || !$this->routeBooking()) {
            return collect();
        }

        $contacts = $this->routeBooking()->contacts()->whereKey($ids->all())->get()->keyBy('id');

        return $ids->map(fn (int $id) => $contacts->get($id))->filter()->values();
    }
}
