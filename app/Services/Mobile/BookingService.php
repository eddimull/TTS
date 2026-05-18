<?php

namespace App\Services\Mobile;

use App\Models\Bookings;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingService
{
    /**
     * Build the per-event `additional_data` blob (load-in / soundcheck / etc.)
     * anchored to a single event's start and end.
     */
    public function buildAdditionalData(int $eventTypeId, Carbon $startDt, Carbon $endDt): array
    {
        $additionalData = [
            'times' => [
                ['title' => 'Load In',    'time' => $startDt->copy()->subHours(4)->format('Y-m-d H:i')],
                ['title' => 'Soundcheck', 'time' => $startDt->copy()->subHours(3)->format('Y-m-d H:i')],
                ['title' => 'Quiet',      'time' => $startDt->copy()->subHours(1)->format('Y-m-d H:i')],
                ['title' => 'End Time',   'time' => $endDt->format('Y-m-d H:i')],
            ],
            'backline_provided' => false,
            'production_needed' => true,
            'color'             => 'TBD',
            'public'            => true,
            'outside'           => false,
            'lodging'           => [
                ['title' => 'Provided',  'type' => 'checkbox', 'data' => false],
                ['title' => 'location',  'type' => 'text',     'data' => 'TBD'],
                ['title' => 'check_in',  'type' => 'text',     'data' => 'TBD'],
                ['title' => 'check_out', 'type' => 'text',     'data' => 'TBD'],
            ],
        ];

        if ($eventTypeId === 1) {
            $additionalData['wedding'] = [
                'onsite' => true,
                'dances' => [
                    ['title' => 'first_dance',     'data' => 'TBD'],
                    ['title' => 'father_daughter', 'data' => 'TBD'],
                    ['title' => 'mother_son',      'data' => 'TBD'],
                    ['title' => 'money_dance',     'data' => 'TBD'],
                    ['title' => 'bouquet_garter',  'data' => 'TBD'],
                ],
            ];
            $additionalData['times'][] = ['title' => 'Ceremony', 'time' => $startDt->format('Y-m-d H:i')];
            $additionalData['onsite']  = true;
            $additionalData['public']  = false;
        }

        return $additionalData;
    }

    public function redistributeEventValues(Bookings $booking): void
    {
        $events = $booking->events()->get();
        if ($events->isEmpty()) {
            return;
        }
        $share = $booking->price / $events->count();
        foreach ($events as $event) {
            $event->update(['value' => $share]);
        }
    }

    public function getInitialTerms(): array
    {
        $path = storage_path('app/contract/InitialTerms.json');
        if (file_exists($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Create a single Event under a booking from one entry of the create
     * payload's `events` array.
     *
     * @param  array  $eventData  Validated event fields: title, date,
     *                            start_time (required), end_time?, venue_name?,
     *                            venue_address?, price?.
     */
    public function createBookingEvent(Bookings $booking, array $eventData, int $eventTypeId): void
    {
        $defaultRoster = $booking->band->defaultRoster ?? null;

        $startDt = Carbon::parse($eventData['date'] . ' ' . $eventData['start_time']);
        // end_time is optional; when absent, default the event to two hours.
        $endTime = $eventData['end_time']
            ?? $startDt->copy()->addHours(2)->format('H:i');
        $endDt = Carbon::parse($eventData['date'] . ' ' . $endTime);

        $eventAttrs = [
            'title'           => $eventData['title'],
            'date'            => $eventData['date'],
            'start_time'      => $eventData['start_time'],
            'end_time'        => $endTime,
            'venue_address'   => $eventData['venue_address'] ?? null,
            'event_type_id'   => $eventTypeId,
            'value'           => $eventData['price'] ?? 0,
            'additional_data' => $this->buildAdditionalData($eventTypeId, $startDt, $endDt),
            'key'             => Str::uuid()->toString(),
            'roster_id'       => $defaultRoster?->id,
        ];

        // venue_name defaults to 'TBD' at schema level; omit when blank so the
        // default fires rather than inserting null into a NOT NULL column.
        if (!empty($eventData['venue_name'])) {
            $eventAttrs['venue_name'] = $eventData['venue_name'];
        }

        $booking->events()->create($eventAttrs);
    }
}
