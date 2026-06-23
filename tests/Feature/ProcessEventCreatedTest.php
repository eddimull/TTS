<?php

namespace Tests\Feature;

use App\Jobs\ProcessEventCreated;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
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

        Log::spy();

        (new ProcessEventCreated($event))->handle();

        // The job swallows throws into Log::error, so a "no throw" assertion
        // alone would pass even when the bug is present. Assert the exact
        // production crash signature was never logged.
        Log::shouldNotHaveReceived('error', [Mockery::on(
            fn ($message) => is_string($message)
                && str_contains($message, 'Attempt to read property "public" on string')
        )]);

        // Job ran to completion; no Google event row created (no calendar).
        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
        ]);
    }

    public function test_handle_does_not_crash_when_additional_data_lacks_public_key(): void
    {
        // TTS-BAND-ZJ: additional_data is a valid object but has no `public`
        // key. Reading ->public directly raises "Undefined property:
        // stdClass::$public" under PHP 8. The job must treat a missing key as
        // not-public, not crash.
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $booking->id,
        ]);

        // Well-formed JSON object without a `public` key.
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(['times' => []])]);

        Log::spy();

        (new ProcessEventCreated($event))->handle();

        Log::shouldNotHaveReceived('error', [Mockery::on(
            fn ($message) => is_string($message)
                && str_contains($message, 'Undefined property: stdClass::$public')
        )]);

        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
        ]);
    }
}
