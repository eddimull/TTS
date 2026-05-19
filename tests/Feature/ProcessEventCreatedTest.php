<?php

namespace Tests\Feature;

use App\Jobs\ProcessEventCreated;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessEventCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_does_not_crash_on_double_encoded_additional_data(): void
    {
        // A band with no calendars -> writeToGoogleCalendar returns false, so
        // the job exercises the additional_data->public branch (line 48 of
        // ProcessEventCreated) without needing real Google API calls.
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $booking->id,
        ]);

        // Double-encode additional_data, the exact corruption from TTS-BAND-11E.
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(json_encode(['public' => true]))]);

        // Capture log calls so we can assert the production crash signature
        // ("Attempt to read property public on string") never appears, even
        // though ProcessEventCreated::handle() swallows exceptions via try/catch.
        $loggedErrors = [];
        Log::shouldReceive('info')->andReturnUsing(function () {});
        Log::shouldReceive('debug')->andReturnUsing(function () {});
        Log::shouldReceive('warning')->andReturnUsing(function () {});
        Log::shouldReceive('error')->andReturnUsing(function ($message) use (&$loggedErrors) {
            $loggedErrors[] = $message;
        });

        // Must not throw "Attempt to read property public on string".
        (new ProcessEventCreated($event))->handle();

        foreach ($loggedErrors as $message) {
            $this->assertStringNotContainsString(
                'Attempt to read property "public" on string',
                $message,
                'ProcessEventCreated swallowed the TTS-BAND-11E crash via try/catch — accessor regression.',
            );
        }

        // Job ran to completion; no Google event row created (no calendar).
        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
        ]);
    }
}
