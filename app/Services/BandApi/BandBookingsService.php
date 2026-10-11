<?php

namespace App\Services\BandApi;

use App\Models\Bands;
use App\Models\Bookings;
use App\Services\BookingContactService;
use App\Services\Mobile\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Booking reads/writes in the developer API's shape, shared by the REST
 * endpoints (`/booked-dates`, `/bookings`) and the MCP server's booking tools.
 *
 * Every lookup is scoped to the given band; callers resolve which band the
 * caller may act on (API token's band, or an MCP user's permitted band).
 */
class BandBookingsService
{
    private const WITH = ['events', 'contacts', 'payments'];

    /** Fields that live on the booking's events rather than the booking row. */
    private const EVENT_FIELDS = ['date', 'start_time', 'end_time', 'venue_name', 'venue_address'];

    /** Most contacts accepted by one create call (`contact` + `contacts`). */
    private const MAX_CONTACTS = 10;

    public function __construct(
        private BookingService $bookingService,
        private BookingContactService $contacts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function createRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'event_type_id' => ['required', Rule::exists('event_types', 'id')],
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer|min:1|max:24',
            'price' => 'required|numeric|min:0',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'contract_option' => 'required|in:default,none,external',
            'status' => 'nullable|in:draft,pending,confirmed,cancelled',
            'notes' => 'nullable|string',
            'contact' => 'nullable|array',
            ...self::contactRules('contact.', 'required_with:contact'),
            'contacts' => 'nullable|array|max:' . self::MAX_CONTACTS,
            ...self::contactRules('contacts.*.', 'required'),
        ];
    }

    /**
     * Rules for one client contact, keys prefixed (e.g. "contact.").
     *
     * @return array<string, mixed>
     */
    public static function contactRules(string $prefix = '', string $required = 'required'): array
    {
        return [
            "{$prefix}name" => "{$required}|string|max:255",
            "{$prefix}email" => "{$required}|email|max:255",
            "{$prefix}phone" => 'nullable|string|max:255',
            "{$prefix}role" => 'nullable|string|max:255',
            "{$prefix}is_primary" => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'event_type_id' => ['sometimes', 'required', Rule::exists('event_types', 'id')],
            'date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'price' => 'sometimes|required|numeric|min:0',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'contract_option' => 'sometimes|required|in:default,none,external',
            'status' => 'nullable|in:draft,pending,confirmed,cancelled',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * The band's bookings in `/booked-dates` row shape, ordered by first event.
     *
     * Each date filter is its own events constraint (a booking matches when any
     * of its events satisfies that filter), exactly as the REST endpoint has
     * always behaved.
     *
     * @param  array<string, mixed>  $filters  validated {@see DateFilters::rules()} input
     * @return Collection<int, array<string, mixed>>
     */
    public function bookedDates(Bands $band, array $filters = []): Collection
    {
        $query = Bookings::where('band_id', $band->id)
            ->with(['eventType', 'events']);

        foreach (DateFilters::constraints($filters) as [$operator, $value]) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', $operator, $value));
        }

        return $query->get()
            ->sortBy(fn ($booking) => $booking->start_date?->format('Y-m-d H:i') ?? '')
            ->values()
            ->map(fn (Bookings $booking) => [
                'id' => $booking->id,
                'name' => $booking->name,
                'start_date' => $booking->start_date?->format('Y-m-d'),
                'end_date' => $booking->end_date?->format('Y-m-d'),
                'event_count' => $booking->event_count,
                'is_multi_event' => $booking->is_multi_event,
                'venue_summary' => $booking->venue_summary,
                'event_type' => $booking->eventType?->name,
                'event_type_id' => $booking->event_type_id,
                'status' => $booking->status,
                'price' => $booking->price,
                'notes' => $booking->notes,
                'events' => $booking->events->map(fn ($event) => [
                    'id' => $event->id,
                    'date' => $event->date?->format('Y-m-d'),
                    'start_time' => $event->start_time?->format('H:i'),
                    'end_time' => $event->end_time?->format('H:i'),
                    'venue_name' => $event->venue_name,
                    'venue_address' => $event->venue_address,
                ])->values(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(Bands $band): Collection
    {
        return $band->bookings()
            ->with(self::WITH)
            ->get()
            ->map(fn (Bookings $booking) => $this->format($booking));
    }

    /**
     * The band's booking with this id, or null when it does not exist or
     * belongs to another band (callers must not distinguish the two).
     */
    public function find(Bands $band, mixed $id, bool $withRelations = false): ?Bookings
    {
        return Bookings::where('id', $id)
            ->where('band_id', $band->id)
            ->when($withRelations, fn ($q) => $q->with(self::WITH))
            ->first();
    }

    /**
     * Create a booking the same way the mobile app does
     * ({@see BookingService::createBooking()}): contract with the initial terms,
     * one event templated for the event type (timeline, extras, default
     * roster), event value = price. End time is start + duration hours. Any
     * `contact` / `contacts` are then attached; all in one transaction.
     *
     * Unlike mobile/web, status is never derived from contract_option: it is
     * the given `status`, or draft.
     *
     * @param  array<string, mixed>  $validated  {@see createRules()} input
     * @param  int|null  $authorId  defaults to the band's first owner (then member)
     */
    public function create(Bands $band, array $validated, ?int $authorId = null): Bookings
    {
        $startDateTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);

        $event = [
            'title' => $validated['name'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $startDateTime->copy()->addHours((int) $validated['duration'])->format('H:i'),
            'venue_name' => $validated['venue_name'] ?? null,
            'venue_address' => $validated['venue_address'] ?? null,
        ];

        $attributes = collect($validated)
            ->except([...self::EVENT_FIELDS, 'duration', 'contact', 'contacts'])
            ->toArray();
        $attributes['status'] = $validated['status'] ?? 'draft';

        $authorId ??= $band->owners->first()?->user_id
            ?? $band->members->first()?->user_id;

        $contacts = array_values(array_filter([
            $validated['contact'] ?? null,
            ...($validated['contacts'] ?? []),
        ]));

        return DB::transaction(function () use ($band, $attributes, $event, $authorId, $contacts) {
            $booking = $this->bookingService->createBooking($band, $attributes, [$event], $authorId);

            foreach ($contacts as $contact) {
                $this->attachContact($band, $booking, $contact);
            }

            return $booking->load(self::WITH);
        });
    }

    /**
     * Attach a client contact (find-or-create by email within the band).
     *
     * @param  array<string, mixed>  $contact  {@see contactRules()} input
     */
    public function addContact(Bands $band, Bookings $booking, array $contact): Bookings
    {
        $this->attachContact($band, $booking, $contact);

        return $booking->fresh(self::WITH);
    }

    /**
     * is_primary defaults to true while the booking has no other primary
     * contact, so a lead's first contact becomes the primary one.
     *
     * @param  array<string, mixed>  $contact
     */
    private function attachContact(Bands $band, Bookings $booking, array $contact): void
    {
        $contact['is_primary'] ??= ! $booking->contacts()
            ->wherePivot('is_primary', true)
            ->where('contacts.email', '!=', $contact['email'])
            ->exists();

        $this->contacts->attach($band, $booking, $contact);
    }

    /**
     * Update a booking. Date/time/venue fields go to its primary (earliest) event.
     * A price change re-splits the event values, as web and mobile do.
     *
     * @param  array<string, mixed>  $validated  {@see updateRules()} input
     */
    public function update(Bookings $booking, array $validated): Bookings
    {
        $bookingFields = collect($validated)->except(self::EVENT_FIELDS)->toArray();
        $eventFields = collect($validated)->only(self::EVENT_FIELDS)->toArray();

        $booking->update($bookingFields);

        if ($booking->wasChanged('price')) {
            $this->bookingService->redistributeEventValues($booking->fresh());
        }

        if (! empty($eventFields)) {
            $primaryEvent = $booking->events()->orderBy('date')->orderBy('id')->first();
            $primaryEvent?->update($eventFields);
        }

        return $booking->fresh(self::WITH);
    }

    public function delete(Bookings $booking): void
    {
        $booking->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function format(Bookings $booking): array
    {
        return [
            'id' => $booking->id,
            'name' => $booking->name,
            'start_date' => $booking->start_date?->format('Y-m-d'),
            'end_date' => $booking->end_date?->format('Y-m-d'),
            'event_count' => $booking->event_count,
            'is_multi_event' => $booking->is_multi_event,
            'venue_summary' => $booking->venue_summary,
            'price' => $booking->price,
            'status' => $booking->status,
            'contract_option' => $booking->contract_option,
            'notes' => $booking->notes,
            'event_type_id' => $booking->event_type_id,
            'created_at' => $booking->created_at->toISOString(),
            'updated_at' => $booking->updated_at->toISOString(),
            'events' => ($booking->events ?? collect())->map(fn ($event) => [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->date?->format('Y-m-d'),
                'start_time' => $event->start_time?->format('H:i'),
                'end_time' => $event->end_time?->format('H:i'),
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
            ])->values(),
            'contacts' => $booking->contacts ?? [],
            'payments' => $booking->payments ?? [],
            'amount_paid' => $booking->amount_paid ?? 0,
            'amount_due' => $booking->amount_due ?? 0,
            'is_paid' => $booking->is_paid ?? false,
        ];
    }
}
