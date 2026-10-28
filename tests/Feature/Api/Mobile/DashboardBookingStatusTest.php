<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile calendar draws a solid ring for confirmed bookings and a dashed
 * one for draft/pending, so each booking event must carry its booking's status.
 */
class DashboardBookingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_events_carry_their_booking_status(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name'        => 'Status Band',
            'site_name'   => 'status-band-' . uniqid(),
            'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);
        $eventType = EventTypes::factory()->create();

        foreach (['draft', 'pending', 'confirmed', 'cancelled'] as $i => $status) {
            $booking = Bookings::factory()->create([
                'name'    => "Gig {$status}",
                'band_id' => $band->id,
                'status'  => $status,
            ]);
            Events::factory()->create([
                'eventable_id'   => $booking->id,
                'eventable_type' => 'App\\Models\\Bookings',
                'event_type_id'  => $eventType->id,
                'date'           => now()->addDays(7 + $i)->toDateString(),
                'title'          => "Gig {$status}",
            ]);
        }

        $token = $user->createToken('test')->plainTextToken;
        $events = collect(
            $this->withToken($token)->getJson('/api/mobile/dashboard')->assertOk()->json('events')
        );

        foreach (['draft', 'pending', 'confirmed', 'cancelled'] as $status) {
            $event = $events->firstWhere('title', "Gig {$status}");
            $this->assertNotNull($event, "Dashboard should include the {$status} booking");
            $this->assertSame($status, $event['status']);
        }
    }
}
