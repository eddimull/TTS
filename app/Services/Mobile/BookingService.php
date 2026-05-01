<?php

namespace App\Services\Mobile;

use App\Models\Bookings;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingService
{
    public function buildAdditionalData(array $validated, Carbon $startDt, Carbon $endDt): array
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

        if ((int) $validated['event_type_id'] === 1) {
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

    public function createInitialEvent(Bookings $booking, array $validated, array $additionalData): void
    {
        $defaultRoster = $booking->band->defaultRoster ?? null;

        $booking->events()->create([
            'title'           => $booking->name,
            'date'            => $validated['date'],
            'time'            => $validated['start_time'],
            'event_type_id'   => $validated['event_type_id'],
            'value'           => $validated['price'] ?? 0,
            'additional_data' => $additionalData,
            'key'             => Str::uuid()->toString(),
            'roster_id'       => $defaultRoster?->id,
        ]);
    }
}
