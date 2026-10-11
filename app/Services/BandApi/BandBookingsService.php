<?php

namespace App\Services\BandApi;

use App\Models\Bands;
use App\Models\Bookings;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
     * Create a booking and its initial event. End time is start + duration hours.
     *
     * @param  array<string, mixed>  $validated  {@see createRules()} input
     * @param  int|null  $authorId  defaults to the band's first owner (then member)
     */
    public function create(Bands $band, array $validated, ?int $authorId = null): Bookings
    {
        // Capture event-level fields before stripping them from the booking payload
        $eventDate = $validated['date'];
        $eventStart = $validated['start_time'];
        $eventDuration = $validated['duration'];
        $venueName = $validated['venue_name'] ?? null;
        $venueAddress = $validated['venue_address'] ?? null;

        // Calculate end_time from duration
        $startDateTime = Carbon::parse($eventDate . ' ' . $eventStart);
        $eventEnd = $startDateTime->copy()->addHours($eventDuration)->format('H:i');

        // Strip event-level fields from the booking payload
        $bookingData = collect($validated)->except([...self::EVENT_FIELDS, 'duration'])->toArray();

        $bookingData['band_id'] = $band->id;
        $bookingData['status'] = $validated['status'] ?? 'draft';
        $bookingData['author_id'] = $authorId
            ?? $band->owners->first()?->user_id
            ?? $band->members->first()?->user_id;

        $booking = Bookings::create($bookingData);

        // Create the initial event with the date/time/venue data
        $booking->events()->create([
            'event_type_id' => $booking->event_type_id,
            'key' => Str::uuid(),
            'title' => $booking->name,
            'date' => $eventDate,
            'start_time' => $eventStart,
            'end_time' => $eventEnd,
            'venue_name' => $venueName,
            'venue_address' => $venueAddress,
        ]);

        return $booking->load(self::WITH);
    }

    /**
     * Update a booking. Date/time/venue fields go to its primary (earliest) event.
     *
     * @param  array<string, mixed>  $validated  {@see updateRules()} input
     */
    public function update(Bookings $booking, array $validated): Bookings
    {
        $bookingFields = collect($validated)->except(self::EVENT_FIELDS)->toArray();
        $eventFields = collect($validated)->only(self::EVENT_FIELDS)->toArray();

        $booking->update($bookingFields);

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
