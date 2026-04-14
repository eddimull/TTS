<?php

namespace App\Services\Mobile;

use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;

class RehearsalService
{
    public function formatSummary(Rehearsal $rehearsal): array
    {
        $event = $rehearsal->events->first();
        $date  = $event
            ? (is_string($event->date) ? $event->date : $event->date->format('Y-m-d'))
            : null;
        $time  = $event && $event->time
            ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
            : null;

        return [
            'id'            => $rehearsal->id,
            'date'          => $date,
            'time'          => $time,
            'venue_name'    => $rehearsal->venue_name,
            'venue_address' => $rehearsal->venue_address,
            'is_cancelled'  => $rehearsal->is_cancelled,
            'notes'         => $rehearsal->notes,
            'event_key'     => $event?->key,
        ];
    }

    public function formatDetail(Rehearsal $rehearsal, ?string $fallbackDate = null): array
    {
        $event = $rehearsal->events->first();
        $date  = $event
            ? (is_string($event->date) ? $event->date : $event->date->format('Y-m-d'))
            : $fallbackDate;
        $time  = $event && $event->time
            ? (is_string($event->time) ? $event->time : $event->time->format('H:i'))
            : null;

        $schedule = $rehearsal->rehearsalSchedule;

        $associatedBookings = $rehearsal->bookings->map(function ($booking) {
            $bookingDate = is_string($booking->date) ? $booking->date : $booking->date->format('Y-m-d');
            return [
                'id'   => $booking->id,
                'name' => $booking->name,
                'date' => $bookingDate,
            ];
        })->values()->all();

        return [
            'id'            => $rehearsal->id,
            'date'          => $date,
            'time'          => $time,
            'venue_name'    => $rehearsal->venue_name,
            'venue_address' => $rehearsal->venue_address,
            'is_cancelled'  => $rehearsal->is_cancelled,
            'notes'         => $rehearsal->notes,
            'event_key'     => $event?->key,
            'schedule'      => $schedule ? [
                'id'            => $schedule->id,
                'name'          => $schedule->name,
                'location_name' => $schedule->location_name,
            ] : null,
            'associated_bookings' => $associatedBookings,
        ];
    }

    /**
     * Parse a virtual rehearsal key and return [scheduleId, date], or abort(404).
     *
     * @return array{0: int, 1: string}
     */
    public function parseVirtualKey(string $key): array
    {
        if (!preg_match('/^virtual-rehearsal-(\d+)-(\d{4}-\d{2}-\d{2})$/', $key, $matches)) {
            abort(404, 'Rehearsal not found.');
        }

        return [(int) $matches[1], $matches[2]];
    }

    /**
     * Find an existing Rehearsal stub for the given schedule + date, or create one
     * along with its anchoring Events record.
     */
    public function findOrCreateStub(RehearsalSchedule $schedule, string $date, string $key): Rehearsal
    {
        $rehearsal = Rehearsal::whereHas('events', fn ($q) => $q->where('date', $date))
            ->where('rehearsal_schedule_id', $schedule->id)
            ->first();

        if ($rehearsal) {
            return $rehearsal;
        }

        $rehearsal = Rehearsal::create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $schedule->band->id,
            'venue_name'            => $schedule->location_name,
            'venue_address'         => $schedule->location_address,
            'is_cancelled'          => false,
            'notes'                 => null,
        ]);

        $rehearsal->events()->create([
            'key'           => $key,
            'date'          => $date,
            'title'         => $schedule->name,
            'event_type_id' => EventTypes::where('name', 'Rehearsal')->value('id'),
        ]);

        return $rehearsal;
    }
}
